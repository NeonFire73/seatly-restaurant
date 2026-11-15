<?php

namespace App\Http\Controllers\Site;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\GatewayManager;
use App\Support\Localization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Підставна платіжна сторінка (частина 2, п. 6.5): стоїть на місці сторінки Monobank, коли PAYMENT_GATEWAY=fake.
 * Кнопки на ній роблять те саме, що справжній вебхук, — викликають HandlePaymentWebhook.
 */
class FakePaymentController extends Controller
{
    public function show(Payment $payment, GatewayManager $gateways): View|RedirectResponse
    {
        $this->guard($payment, $gateways);

        if (! $payment->status->isPending()) {
            return redirect()->to($this->returnUrl($payment));
        }

        return view('site.fake-pay', ['payment' => $payment->load(['order.table', 'reservation'])]);
    }

    public function submit(Request $request, Payment $payment, GatewayManager $gateways, FakeGateway $gateway, HandlePaymentWebhook $handleWebhook): RedirectResponse
    {
        $this->guard($payment, $gateways);

        $status = match ($request->input('action')) {
            'success' => PaymentStatus::Success,
            'failure' => PaymentStatus::Failure,
            default => null,
        };

        // «Не платити»: гість просто повертається на сайт, рахунок лишається неоплаченим
        if ($status !== null && $payment->status->isPending()) {
            $data = $gateway->simulate($payment, $status);

            $log = PaymentWebhookLog::create([
                'provider' => FakeGateway::NAME,
                'provider_invoice_id' => $data->invoiceId,
                'payload' => ['invoiceId' => $data->invoiceId, 'status' => $status->value, 'modifiedDate' => $data->modifiedAt?->toIso8601String()],
                'signature_valid' => true,
            ]);

            $handleWebhook->handle($data, $log);
        }

        return redirect()->to($this->returnUrl($payment));
    }

    private function guard(Payment $payment, GatewayManager $gateways): void
    {
        abort_unless($gateways->isFake() && $payment->provider === FakeGateway::NAME, 404);

        $locale = $payment->locale();

        if (Localization::isSupported($locale)) {
            app()->setLocale((string) $locale);
        }
    }

    private function returnUrl(Payment $payment): string
    {
        return Localization::route('payment.return', ['payment' => $payment->id]);
    }
}
