<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\OpenOrder;
use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\StartPayment;
use App\Actions\Payments\SyncPaymentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\PaymentException;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\User;
use App\Services\Payments\MonobankGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'seatly.payments.gateway' => 'monobank',
        'services.monobank.token' => 'test-token',
        'services.monobank.webhook_url' => null,
    ]);

    // Тестова пара ключів на тій самій кривій, що й у Monobank (prime256v1). Ключі лежать файлами:
    // на Windows openssl_pkey_new() без налаштованого openssl.cnf нового ключа не створює
    $this->privateKey = testKey('monobank-test-key');
    $this->publicPem = openssl_pkey_get_details($this->privateKey)['key'];

    $this->table = DiningTable::factory()->create(['number' => '7']);
    $this->order = app(OpenOrder::class)->handle($this->table, User::factory()->waiter()->create());
    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['name' => ['uk' => 'Борщ', 'en' => 'Borscht'], 'price' => 18000]), 2);
});

function testKey(string $name): OpenSSLAsymmetricKey
{
    return openssl_pkey_get_private(file_get_contents(base_path("tests/Fixtures/{$name}.pem")));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeMonobank(array $overrides = []): void
{
    Http::fake(array_merge([
        'api.monobank.ua/api/merchant/invoice/create' => Http::response(['invoiceId' => 'p2_9ZgpZVsl3', 'pageUrl' => 'https://pay.mbnk.biz/p2_9ZgpZVsl3']),
        'api.monobank.ua/api/merchant/invoice/remove' => Http::response([]),
        'api.monobank.ua/api/merchant/pubkey' => Http::response(['key' => base64_encode(test()->publicPem)]),
    ], $overrides));
}

/**
 * Запит вебхука з підписом ECDSA над тілом, як його надсилає Monobank.
 *
 * @param  array<string, mixed>  $payload
 */
function signedWebhook(array $payload, mixed $privateKey = null, ?string $signature = null)
{
    $body = json_encode($payload);

    if ($signature === null) {
        openssl_sign($body, $raw, $privateKey ?? test()->privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($raw);
    }

    return test()->call('POST', '/webhooks/monobank', [], [], [], ['HTTP_X_SIGN' => $signature, 'CONTENT_TYPE' => 'application/json'], $body);
}

it('обирає шлюз за конфігурацією', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(MonobankGateway::class);
});

it('створює рахунок у Monobank із кошиком, чайовими й адресами повернення та вебхука', function () {
    fakeMonobank();

    $payment = app(StartPayment::class)->handle($this->order, 3600, null, 'en');

    expect($payment->provider)->toBe('monobank')
        ->and($payment->provider_invoice_id)->toBe('p2_9ZgpZVsl3')
        ->and($payment->page_url)->toBe('https://pay.mbnk.biz/p2_9ZgpZVsl3');

    Http::assertSent(function (Request $request) use ($payment) {
        $basket = $request['merchantPaymInfo']['basketOrder'];

        return $request->url() === 'https://api.monobank.ua/api/merchant/invoice/create'
            && $request->hasHeader('X-Token', 'test-token')
            && $request['amount'] === 39600
            && $request['ccy'] === 980
            && $request['validity'] > 800 && $request['validity'] <= 900
            && $request['merchantPaymInfo']['reference'] === $payment->id
            && $request['redirectUrl'] === url('/en/pay/'.$payment->id.'/return')
            && $request['webHookUrl'] === url('/webhooks/monobank')
            && count($basket) === 2
            && $basket[0] === ['name' => 'Borscht', 'qty' => 2, 'sum' => 18000, 'total' => 36000, 'unit' => 'pcs', 'code' => 'dish-'.$this->order->items()->first()->dish_id]
            && $basket[1]['code'] === 'tip' && $basket[1]['sum'] === 3600;
    });
});

it('не лишає платежу, якщо Monobank не створив рахунок', function () {
    Http::fake(['api.monobank.ua/*' => Http::response(['errCode' => 'FORBIDDEN', 'errText' => 'forbidden'], 403)]);

    expect(fn () => app(StartPayment::class)->handle($this->order))->toThrow(PaymentException::class)
        ->and(Payment::count())->toBe(0);
});

it('без токена шлюз не працює', function () {
    config(['services.monobank.token' => null]);
    Http::fake();

    expect(fn () => app(StartPayment::class)->handle($this->order))->toThrow(PaymentException::class);
    Http::assertNothingSent();
});

it('перетворює статуси Monobank на статуси системи', function (string $monobank, PaymentStatus $expected) {
    $data = app(MonobankGateway::class)->statusFromPayload([
        'invoiceId' => 'p2_x',
        'status' => $monobank,
        'modifiedDate' => '2026-11-16T10:15:22Z',
        'failureReason' => 'Неправильний CVV код',
        'cancelList' => [
            ['status' => 'success', 'amount' => 1000],
            ['status' => 'processing', 'amount' => 500],
        ],
    ]);

    expect($data->status)->toBe($expected)
        ->and($data->invoiceId)->toBe('p2_x')
        ->and($data->modifiedAt->toIso8601String())->toBe('2026-11-16T10:15:22+00:00')
        ->and($data->failureReason)->toBe('Неправильний CVV код')
        ->and($data->refundedAmount)->toBe(1000);
})->with([
    ['created', PaymentStatus::Created],
    ['processing', PaymentStatus::Processing],
    ['hold', PaymentStatus::Processing],
    ['success', PaymentStatus::Success],
    ['failure', PaymentStatus::Failure],
    ['expired', PaymentStatus::Expired],
    ['reversed', PaymentStatus::Refunded],
]);

it('відкидає дані з невідомим статусом', function () {
    expect(fn () => app(MonobankGateway::class)->statusFromPayload(['invoiceId' => 'p2_x', 'status' => 'weird']))
        ->toThrow(GatewayException::class);
});

it('приймає вебхук із чинним підписом і позначає платіж сплаченим', function () {
    fakeMonobank();
    $payment = app(StartPayment::class)->handle($this->order, 3600);

    signedWebhook([
        'invoiceId' => 'p2_9ZgpZVsl3',
        'status' => 'success',
        'amount' => 39600,
        'ccy' => 980,
        'modifiedDate' => '2026-11-16T10:15:22Z',
        'walletData' => ['cardToken' => 'secret-token'],
    ])->assertOk();

    $log = PaymentWebhookLog::sole();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success)
        ->and($this->order->fresh()->amountDue())->toBe(0)
        ->and($log->signature_valid)->toBeTrue()
        ->and($log->processed_at)->not->toBeNull()
        ->and($log->provider_invoice_id)->toBe('p2_9ZgpZVsl3')
        // Токен картки в журнал не потрапляє
        ->and($log->payload)->not->toHaveKey('walletData');

    // Ключ узято з кешу: другий вебхук не запитує його знову
    signedWebhook(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'success', 'modifiedDate' => '2026-11-16T10:15:22Z'])->assertOk();

    Http::assertSentCount(2);
    expect($this->order->fresh()->paid_total)->toBe(36000);
});

it('відхиляє вебхук із неправильним підписом і фіксує його в журналі', function () {
    fakeMonobank();
    $payment = app(StartPayment::class)->handle($this->order);
    $stranger = testKey('monobank-other-key');
    $payload = ['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'success', 'modifiedDate' => '2026-11-16T10:15:22Z'];

    signedWebhook($payload, $stranger)->assertForbidden();
    signedWebhook($payload, null, 'not-base64!')->assertForbidden();
    signedWebhook($payload, null, '')->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Created)
        ->and($this->order->fresh()->paid_total)->toBe(0)
        ->and(PaymentWebhookLog::count())->toBe(3)
        ->and(PaymentWebhookLog::where('signature_valid', false)->count())->toBe(3)
        ->and(PaymentWebhookLog::first()->error)->toBe('Недійсний підпис')
        ->and(PaymentWebhookLog::first()->processed_at)->toBeNull();
});

it('запитує відкритий ключ повторно, якщо Monobank його замінив', function () {
    fakeMonobank();
    $payment = app(StartPayment::class)->handle($this->order);
    cache()->forever('monobank.pubkey', openssl_pkey_get_details(testKey('monobank-other-key'))['key']);

    signedWebhook(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'success', 'modifiedDate' => '2026-11-16T10:15:22Z'])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success)
        ->and(cache()->get('monobank.pubkey'))->toBe($this->publicPem);
});

it('фіксує вебхук про невідомий рахунок, не падаючи', function () {
    fakeMonobank();

    signedWebhook(['invoiceId' => 'p2_unknown', 'status' => 'success'])->assertOk();

    expect(PaymentWebhookLog::sole()->error)->toBe('Платіж із таким рахунком не знайдено');
});

it('вебхук не потребує сесії й CSRF-токена', function () {
    fakeMonobank();

    $response = signedWebhook(['invoiceId' => 'p2_unknown', 'status' => 'created']);

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty();
});

it('запитує статус у Monobank і застосовує його', function () {
    fakeMonobank([
        'api.monobank.ua/api/merchant/invoice/status*' => Http::response(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'expired', 'modifiedDate' => '2026-11-16T10:30:00Z']),
    ]);
    $payment = app(StartPayment::class)->handle($this->order);

    app(SyncPaymentStatus::class)->handle($payment);

    expect($payment->status)->toBe(PaymentStatus::Expired);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/invoice/status?invoiceId=p2_9ZgpZVsl3'));
});

it('повертає кошти через Monobank', function () {
    fakeMonobank([
        'api.monobank.ua/api/merchant/invoice/cancel' => Http::response(['status' => 'success', 'createdDate' => '2026-11-16T11:00:00Z', 'modifiedDate' => '2026-11-16T11:00:00Z']),
        'api.monobank.ua/api/merchant/invoice/status*' => Http::response(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'reversed', 'modifiedDate' => '2026-11-16T11:00:00Z']),
    ]);
    $payment = app(StartPayment::class)->handle($this->order, 3600);
    signedWebhook(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'success', 'modifiedDate' => '2026-11-16T10:15:22Z'])->assertOk();

    app(RefundPayment::class)->handle($payment, User::factory()->manager()->create(), 'Гість передумав');

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(39600)
        ->and($this->order->fresh()->amountDue())->toBe(36000);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/invoice/cancel')
        && $request['invoiceId'] === 'p2_9ZgpZVsl3'
        && ! isset($request['amount']));
});

it('не позначає платіж поверненим, якщо Monobank відмовив', function () {
    fakeMonobank([
        'api.monobank.ua/api/merchant/invoice/cancel' => Http::response(['status' => 'failure']),
    ]);
    $payment = app(StartPayment::class)->handle($this->order);
    signedWebhook(['invoiceId' => 'p2_9ZgpZVsl3', 'status' => 'success', 'modifiedDate' => '2026-11-16T10:15:22Z'])->assertOk();

    expect(fn () => app(RefundPayment::class)->handle($payment))->toThrow(PaymentException::class)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Success);
});
