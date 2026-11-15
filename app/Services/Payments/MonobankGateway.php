<?php

namespace App\Services\Payments;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidWebhookSignature;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Localization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monobank Acquiring (частина 2, п. 6.4). Схему звірено з документацією версії v2410:
 * https://api.monobank.ua/docs/acquiring.html
 */
class MonobankGateway implements PaymentGateway
{
    public const NAME = 'monobank';

    private const UAH = 980;

    private const PUBKEY_CACHE = 'monobank.pubkey';

    private const PUBKEY_FETCHED = 'monobank.pubkey.fetched';

    public function name(): string
    {
        return self::NAME;
    }

    public function createInvoice(Payment $payment): InvoiceData
    {
        $payment->loadMissing(['order.table', 'order.items', 'reservation']);
        $locale = $payment->locale() ?? Localization::main();
        $validity = (int) config('seatly.payments.validity');

        if ($payment->expires_at !== null) {
            $validity = max(60, (int) now()->diffInSeconds($payment->expires_at));
        }

        $response = $this->send('post', '/api/merchant/invoice/create', [
            'amount' => $payment->total(),
            'ccy' => self::UAH,
            'merchantPaymInfo' => [
                'reference' => $payment->id,
                'destination' => $this->destination($payment, $locale),
                'basketOrder' => $this->basket($payment, $locale),
            ],
            'redirectUrl' => Localization::route('payment.return', ['payment' => $payment->id], $locale),
            'webHookUrl' => $this->webhookUrl(),
            'validity' => $validity,
            'paymentType' => 'debit',
        ]);

        $invoiceId = $response->json('invoiceId');
        $pageUrl = $response->json('pageUrl');

        if (! is_string($invoiceId) || ! is_string($pageUrl)) {
            throw new GatewayException('Monobank не повернув ідентифікатор рахунку.');
        }

        return new InvoiceData($invoiceId, $pageUrl);
    }

    public function getStatus(Payment $payment): PaymentStatusData
    {
        $response = $this->send('get', '/api/merchant/invoice/status', ['invoiceId' => $payment->provider_invoice_id]);

        return $this->statusFromPayload((array) $response->json());
    }

    public function invalidate(Payment $payment): void
    {
        $this->send('post', '/api/merchant/invoice/remove', ['invoiceId' => $payment->provider_invoice_id]);
    }

    public function refund(Payment $payment, ?int $amount = null): void
    {
        $body = [
            'invoiceId' => $payment->provider_invoice_id,
            // Референс повернення: за ним заявку видно у виписці й у списку cancelList
            'extRef' => (string) Str::ulid(),
        ];

        if ($amount !== null) {
            $body['amount'] = $amount;
        }

        $response = $this->send('post', '/api/merchant/invoice/cancel', $body);

        if ($response->json('status') === 'failure') {
            throw new GatewayException('Monobank відхилив повернення коштів.');
        }
    }

    public function parseWebhook(Request $request): PaymentStatusData
    {
        $body = $request->getContent();
        $signature = base64_decode((string) $request->header('X-Sign'), true);

        if ($signature === false || $signature === '' || ! $this->verify($body, $signature)) {
            throw new InvalidWebhookSignature('Підпис вебхука Monobank недійсний.');
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw new GatewayException('Вебхук Monobank містить некоректний JSON.');
        }

        return $this->statusFromPayload($payload);
    }

    /**
     * Тіло вебхука збігається з відповіддю на запит статусу, тож розбір спільний.
     *
     * @param  array<string, mixed>  $payload
     */
    public function statusFromPayload(array $payload): PaymentStatusData
    {
        $invoiceId = $payload['invoiceId'] ?? null;
        $status = $this->mapStatus($payload['status'] ?? null);

        if (! is_string($invoiceId) || $invoiceId === '' || $status === null) {
            throw new GatewayException('У даних Monobank немає ідентифікатора рахунку або відомого статусу.');
        }

        $modifiedAt = null;

        if (is_string($payload['modifiedDate'] ?? null)) {
            try {
                $modifiedAt = CarbonImmutable::parse($payload['modifiedDate'])->utc();
            } catch (Throwable) {
                $modifiedAt = null;
            }
        }

        $refunded = 0;

        foreach ((array) ($payload['cancelList'] ?? []) as $cancel) {
            if (is_array($cancel) && ($cancel['status'] ?? null) === 'success') {
                $refunded += (int) ($cancel['amount'] ?? 0);
            }
        }

        $failureReason = null;

        if (is_string($payload['failureReason'] ?? null) && $payload['failureReason'] !== '') {
            $failureReason = mb_substr($payload['failureReason'], 0, 255);
        }

        return new PaymentStatusData($invoiceId, $status, $modifiedAt, $failureReason, $refunded);
    }

    private function destination(Payment $payment, string $locale): string
    {
        if ($payment->reservation !== null) {
            return trans('payment.invoice.reservation', ['code' => $payment->reservation->code], $locale);
        }

        return trans('payment.invoice.destination', [
            'number' => $payment->order?->number,
            'table' => $payment->order?->table->number,
        ], $locale);
    }

    private function webhookUrl(): string
    {
        $configured = (string) config('services.monobank.webhook_url');

        if ($configured !== '') {
            return $configured;
        }

        return route('webhooks.monobank');
    }

    private function mapStatus(mixed $status): ?PaymentStatus
    {
        return match ($status) {
            'created' => PaymentStatus::Created,
            'processing', 'hold' => PaymentStatus::Processing,
            'success' => PaymentStatus::Success,
            'failure' => PaymentStatus::Failure,
            'expired' => PaymentStatus::Expired,
            'reversed' => PaymentStatus::Refunded,
            default => null,
        };
    }

    /**
     * Кошик для платіжної сторінки. Позиції перелічуються, лише коли гість платить увесь рахунок:
     * якщо частину вже сплачено, сума позицій не збіглася б із сумою рахунку, тож іде один загальний рядок.
     *
     * @return list<array<string, mixed>>
     */
    private function basket(Payment $payment, string $locale): array
    {
        $unit = trans('payment.invoice.unit', [], $locale);
        $basket = [];
        $items = collect();

        if ($payment->order !== null) {
            $items = $payment->order->items->filter(fn (OrderItem $item) => $item->status !== OrderItemStatus::Cancelled);
        }

        $itemsTotal = (int) $items->sum(fn (OrderItem $item) => $item->total());

        if ($payment->amount === 0) {
            // Платіж лише за бронювання: страв у кошику немає
        } elseif ($itemsTotal === $payment->amount) {
            foreach ($items as $item) {
                $basket[] = [
                    'name' => $item->getTranslation('name', $locale),
                    'qty' => $item->quantity,
                    'sum' => $item->price,
                    'total' => $item->total(),
                    'unit' => $unit,
                    'code' => 'dish-'.$item->dish_id,
                ];
            }
        } else {
            $basket[] = [
                'name' => trans('payment.invoice.bill_part', ['number' => $payment->order?->number], $locale),
                'qty' => 1,
                'sum' => $payment->amount,
                'total' => $payment->amount,
                'unit' => $unit,
                'code' => 'order-'.$payment->order_id,
            ];
        }

        if ($payment->booking_fee_amount > 0) {
            $basket[] = [
                'name' => trans('payment.invoice.booking_fee', [], $locale),
                'qty' => 1,
                'sum' => $payment->booking_fee_amount,
                'total' => $payment->booking_fee_amount,
                'unit' => $unit,
                'code' => 'booking-fee',
            ];
        }

        if ($payment->tip_amount > 0) {
            $basket[] = [
                'name' => trans('payment.invoice.tip', [], $locale),
                'qty' => 1,
                'sum' => $payment->tip_amount,
                'total' => $payment->tip_amount,
                'unit' => $unit,
                'code' => 'tip',
            ];
        }

        return $basket;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(string $method, string $path, array $data): Response
    {
        try {
            $response = $this->client()->{$method}($path, $data);
        } catch (ConnectionException) {
            throw new GatewayException('Monobank недоступний: немає з\'єднання.');
        }

        if ($response->failed()) {
            // У журнал іде лише код і текст помилки: тіло відповіді може містити дані картки, а заголовки — токен
            $reason = $response->json('errText') ?? $response->json('errCode') ?? '';

            throw new GatewayException(trim("Monobank відповів помилкою {$response->status()}. {$reason}"));
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        $token = (string) config('services.monobank.token');

        if ($token === '') {
            throw new GatewayException('Не задано MONOBANK_TOKEN у файлі .env.');
        }

        return Http::baseUrl((string) config('services.monobank.base_url'))
            ->withHeaders(['X-Token' => $token])
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(5);
    }

    /**
     * Ключ кешується; якщо підпис не зійшовся, ключ запитується повторно один раз — Monobank міг його замінити.
     * Повторний запит дозволено не частіше ніж раз на хвилину, щоб потік підроблених вебхуків
     * не вичерпав ліміт запитів до API.
     */
    private function verify(string $body, string $signature): bool
    {
        $cached = Cache::get(self::PUBKEY_CACHE);

        if (is_string($cached) && $this->signatureMatches($body, $signature, $cached)) {
            return true;
        }

        if (! Cache::add(self::PUBKEY_FETCHED, true, 60)) {
            return false;
        }

        $fresh = $this->fetchPublicKey();

        if ($fresh === null || $fresh === $cached) {
            return false;
        }

        Cache::forever(self::PUBKEY_CACHE, $fresh);

        return $this->signatureMatches($body, $signature, $fresh);
    }

    /**
     * Підпис — ECDSA над SHA-256 від тіла запиту, у форматі ASN.1.
     */
    private function signatureMatches(string $body, string $signature, string $pem): bool
    {
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            return false;
        }

        return openssl_verify($body, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function fetchPublicKey(): ?string
    {
        try {
            $encoded = $this->send('get', '/api/merchant/pubkey', [])->json('key');
        } catch (GatewayException $e) {
            report($e);

            return null;
        }

        if (! is_string($encoded)) {
            return null;
        }

        $pem = base64_decode($encoded, true);

        if ($pem === false || $pem === '') {
            return null;
        }

        return $pem;
    }
}
