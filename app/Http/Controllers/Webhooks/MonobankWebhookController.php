<?php

namespace App\Http\Controllers\Webhooks;

use App\Exceptions\Payments\GatewayException;
use App\Exceptions\Payments\InvalidWebhookSignature;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\MonobankGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Вебхук Monobank (частина 2, п. 6.4): записати в журнал, перевірити підпис, поставити обробку в чергу
 * й відразу відповісти. Без чинного підпису дані не обробляються.
 */
class MonobankWebhookController extends Controller
{
    // Справжній вебхук — кілька кілобайтів; більше тіло в журнал не пишеться
    private const MAX_BODY = 65536;

    public function __invoke(Request $request, MonobankGateway $gateway): Response
    {
        $body = $request->getContent();

        if (strlen($body) > self::MAX_BODY) {
            return response()->noContent(413);
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            $payload = [];
        }

        $invoiceId = null;

        if (is_string($payload['invoiceId'] ?? null)) {
            $invoiceId = mb_substr($payload['invoiceId'], 0, 255);
        }

        try {
            $data = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature) {
            // Від непідписаного запиту зберігається лише те, що потрібно для розслідування, а не все тіло
            $this->log($invoiceId, ['status' => $this->scalar($payload['status'] ?? null)], false, 'Недійсний підпис');

            return response()->noContent(403);
        } catch (GatewayException $e) {
            $this->log($invoiceId, $this->safe($payload), true, $e->getMessage());

            return response()->noContent(400);
        }

        $log = $this->log($invoiceId, $this->safe($payload), true);

        ProcessPaymentWebhook::dispatch($log->id, $data);

        return response()->noContent(200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(?string $invoiceId, array $payload, bool $signatureValid, ?string $error = null): PaymentWebhookLog
    {
        return PaymentWebhookLog::create([
            'provider' => MonobankGateway::NAME,
            'provider_invoice_id' => $invoiceId,
            'payload' => $payload,
            'signature_valid' => $signatureValid,
            'error' => $error,
        ]);
    }

    /**
     * Токен збереженої картки в журнал не потрапляє (частина 2, розділ 9).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safe(array $payload): array
    {
        unset($payload['walletData']);

        return $payload;
    }

    private function scalar(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return mb_substr($value, 0, 50);
    }
}
