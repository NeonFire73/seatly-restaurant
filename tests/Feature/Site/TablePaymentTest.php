<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RecordOfflinePayment;
use App\Actions\Payments\HandlePaymentWebhook;
use App\Actions\Payments\StartPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Livewire\PaymentResult;
use App\Livewire\TablePage;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentStatusData;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['number' => '12']);
    $this->waiter = User::factory()->waiter()->create(['name' => 'Ірина Бондар']);
    $this->order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    app(AddOrderItem::class)->handle($this->order, Dish::factory()->create(['name' => ['uk' => 'Борщ'], 'price' => 20000]), 2);
});

function tablePage()
{
    return Livewire::test(TablePage::class, ['token' => test()->table->qr_token]);
}

/**
 * Підсумок панелі оплати: рахунок, чайові, разом. assertSeeInOrder тут не годиться: після виклику методу
 * Livewire відповідає JSON, де кирилицю екрановано.
 *
 * @return list<string>
 */
function paySummary($page): array
{
    $summary = Str::after($page->html(), 'id="pay-title"');
    preg_match_all('/<dd[^>]*>([^<]+)<\/dd>/u', $summary, $matches);

    return array_map(trim(...), $matches[1]);
}

it('показує кнопку «Оплатити», коли є сума до сплати', function () {
    tablePage()
        ->assertSee(__('table.pay.button'))
        ->assertDontSee(__('table.pay.title'));

    app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Cash, $this->waiter);

    tablePage()
        ->assertDontSeeHtml('wire:click="openPay"')
        ->assertSee(__('table.bill.paid_in_full'));
});

it('відкриває панель оплати з типовими чайовими й іменем офіціанта', function () {
    $page = tablePage()
        ->call('openPay')
        ->assertSet('paying', true)
        ->assertSet('tipPercent', 10)
        ->assertSee(__('table.pay.title'))
        ->assertSee(__('table.pay.waiter', ['name' => 'Ірина Бондар']));

    expect(paySummary($page))->toBe(['400 ₴', '40 ₴', '440 ₴'])
        ->and(paySummary($page->call('selectTip', 15)))->toBe(['400 ₴', '60 ₴', '460 ₴'])
        ->and(paySummary($page->call('selectTip', 0)))->toBe(['400 ₴', '0 ₴', '400 ₴']);

    $page->call('closePay')
        ->assertSet('paying', false)
        ->assertDontSee(__('table.pay.title'));
});

it('не показує ім\'я офіціанта, якщо заклад вимкнув цей показ', function () {
    Setting::put('tips', ['show_waiter_name' => false, 'default_percent' => 5]);

    tablePage()
        ->call('openPay')
        ->assertSet('tipPercent', 5)
        ->assertDontSee('Ірина Бондар');
});

it('створює платіж із чайовими й веде на платіжну сторінку', function () {
    $page = tablePage()->call('openPay')->call('selectTip', 15)->call('pay');

    $payment = Payment::sole();

    $page->assertRedirect($payment->page_url);

    expect($payment->amount)->toBe(40000)
        ->and($payment->tip_amount)->toBe(6000)
        ->and($payment->waiter_id)->toBe($this->waiter->id)
        ->and($payment->status)->toBe(PaymentStatus::Created)
        ->and($payment->locale())->toBe('uk');
});

it('приймає власну суму чайових і відхиляє некоректну чи завелику', function () {
    $page = tablePage()->call('openPay')->call('useCustomTip')->assertSet('customTipMode', true);

    $page->set('customTip', 'багато')->call('pay')->assertHasErrors('customTip');
    $page->set('customTip', '200,01')->call('pay')->assertHasErrors('customTip');

    expect(Payment::count())->toBe(0);

    expect(paySummary($page->set('customTip', '25,50')))->toBe(['400 ₴', '25,50 ₴', '425,50 ₴']);

    $page->call('pay')->assertHasNoErrors();

    expect(Payment::sole()->tip_amount)->toBe(2550);
});

it('порожня власна сума означає оплату без чайових', function () {
    tablePage()->call('openPay')->call('useCustomTip')->call('pay');

    expect(Payment::sole()->tip_amount)->toBe(0);
});

it('не довіряє відсотку чайових із браузера', function () {
    $page = tablePage()->call('openPay');

    $page->set('tipPercent', 90)->assertSet('tipPercent', 10);
    $page->call('selectTip', 77)->assertSet('tipPercent', 10);
});

it('не платить без відкритої панелі й показує нову суму, якщо рахунок змінився', function () {
    $page = tablePage();
    $page->call('pay');

    expect(Payment::count())->toBe(0);

    $page->call('openPay');
    // Офіціант додав страву вже після того, як сторінка порахувала суму
    $order = $this->order;
    $page->call('selectTip', 0);
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 5000]));

    $page->call('pay')->assertRedirect();

    // Сума на сервері завжди свіжа: платіж створено вже на новий рахунок
    expect(Payment::sole()->amount)->toBe(45000);
});

it('закриває панель, якщо рахунок сплатили, поки вона була відкрита', function () {
    $page = tablePage()->call('openPay')->assertSet('paying', true);

    app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Cash, $this->waiter);

    $page->call('$refresh')->assertSet('paying', false)->assertSee(__('table.bill.paid_in_full'));
    $page->call('pay');

    expect(Payment::where('method', PaymentMethod::Online)->count())->toBe(0);
});

it('обмежує частоту створення платежів', function () {
    $page = tablePage()->call('openPay');

    foreach (range(1, 6) as $attempt) {
        $page->call('selectTip', [0, 5, 10, 15][$attempt % 4])->call('pay');
    }

    $count = Payment::count();
    $page->call('selectTip', 0)->call('pay')->assertDispatched('toast', message: __('payment.errors.too_many'));

    expect(Payment::count())->toBe($count);
});

it('підставна платіжна сторінка проводить оплату тим самим обробником, що й вебхук', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);

    $this->get('/fake-pay/'.$payment->id)
        ->assertOk()
        ->assertSee(__('payment.fake.title'))
        ->assertSee('440 ₴')
        ->assertSee('noindex, nofollow', false);

    $this->post('/fake-pay/'.$payment->id, ['action' => 'success'])
        ->assertRedirect('/pay/'.$payment->id.'/return');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success)
        ->and($this->order->fresh()->amountDue())->toBe(0)
        ->and(PaymentWebhookLog::sole()->processed_at)->not->toBeNull();

    // Сплачений рахунок удруге не відкривається й не змінюється
    $this->get('/fake-pay/'.$payment->id)->assertRedirect('/pay/'.$payment->id.'/return');
    $this->post('/fake-pay/'.$payment->id, ['action' => 'failure']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);
});

it('підставна сторінка вміє відхилити платіж і відпустити гостя без оплати', function () {
    $payment = app(StartPayment::class)->handle($this->order, 0, null, 'en');

    $this->post('/fake-pay/'.$payment->id, ['action' => 'leave'])
        ->assertRedirect('/en/pay/'.$payment->id.'/return');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Created);

    $this->post('/fake-pay/'.$payment->id, ['action' => 'failure']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failure)
        ->and($this->order->fresh()->amountDue())->toBe(40000);
});

it('підставна сторінка недоступна зі справжнім шлюзом', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    config(['seatly.payments.gateway' => 'monobank']);

    $this->get('/fake-pay/'.$payment->id)->assertNotFound();
    $this->post('/fake-pay/'.$payment->id, ['action' => 'success'])->assertNotFound();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Created);
});

it('сторінка результату показує статус із бази й не створює нового платежу', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);
    $url = '/pay/'.$payment->id.'/return';

    $this->get($url)
        ->assertOk()
        ->assertSee('noindex, nofollow', false)
        ->assertSee(__('payment.result.unfinished_title'))
        ->assertSee(__('payment.result.continue'))
        ->assertSeeHtml('wire:poll.3s="refreshStatus"');

    // Саме повернення гостя нічого не означає: платіж лишається неоплаченим
    expect($payment->fresh()->status)->toBe(PaymentStatus::Created);

    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Processing));
    $this->get($url)->assertSee(__('payment.result.pending_title'));

    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Success));

    $this->get($url)
        ->assertSee(__('payment.result.success_title'))
        ->assertSeeInOrder([__('payment.result.bill'), '400 ₴', __('payment.result.tip'), '40 ₴', __('payment.result.total'), '440 ₴'])
        ->assertSee(__('payment.result.receipt'))
        ->assertDontSeeHtml('wire:poll');

    $this->get($url)->assertOk();

    expect(Payment::count())->toBe(1);
});

it('сторінка результату пояснює відмову й пропонує спробувати ще раз', function () {
    $payment = app(StartPayment::class)->handle($this->order);
    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Failure, null, 'Technical error 59'));

    $this->get('/en/pay/'.$payment->id.'/return')
        ->assertSee('Payment failed')
        ->assertSee('Try again')
        ->assertSee('/en/t/'.$this->table->qr_token)
        // Технічна причина лишається для адмінки
        ->assertDontSee('Technical error 59');
});

it('сторінка результату сама дізнається про прострочений рахунок', function () {
    $payment = app(StartPayment::class)->handle($this->order);

    $this->travel(16)->minutes();

    Livewire::test(PaymentResult::class, ['paymentId' => $payment->id])
        ->assertSee(__('payment.result.expired_title'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('не відкриває результат і квитанцію для невідомого чи офлайн-платежу', function () {
    $offline = app(RecordOfflinePayment::class)->handle($this->order, PaymentMethod::Cash, $this->waiter, 1000);

    $this->get('/pay/01HZZZZZZZZZZZZZZZZZZZZZZZ/return')->assertNotFound();
    $this->get('/pay/'.$offline->id.'/return')->assertNotFound();
    $this->get('/pay/'.$offline->id.'/receipt')->assertNotFound();
});

it('видає квитанцію лише за сплаченим платежем', function () {
    $payment = app(StartPayment::class)->handle($this->order, 4000);
    $url = '/pay/'.$payment->id.'/receipt';

    $this->get($url)->assertNotFound();

    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Success));

    $this->get($url)
        ->assertOk()
        ->assertSee(__('payment.receipt.title'))
        ->assertSee('Борщ × 2')
        ->assertSeeInOrder([__('payment.receipt.bill_paid'), '400 ₴', __('payment.receipt.tip'), '40 ₴', __('payment.receipt.total'), '440 ₴'])
        ->assertSee($payment->id);
});
