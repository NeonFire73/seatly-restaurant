<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\OpenOrder;
use App\Actions\Orders\RecordOfflinePayment;
use App\Actions\Payments\HandlePaymentWebhook;
use App\Actions\Payments\StartPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Pages\TipsReportPage;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\PaymentWebhookLogs\Pages\ListPaymentWebhookLogs;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\User;
use App\Services\Payments\PaymentStatusData;
use App\Services\Reports\TipsReport;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    $this->table = DiningTable::factory()->create(['number' => '3']);
    $this->waiter = User::factory()->waiter()->create(['name' => 'Ірина Бондар']);
    $this->manager = User::factory()->manager()->create();
    $this->owner = User::factory()->owner()->create();
});

/**
 * Сплачене онлайн замовлення на новому столику: сума рахунку й чайові — у копійках.
 */
function paidOrder(?User $waiter, int $amount, int $tip): Payment
{
    $order = app(OpenOrder::class)->handle(DiningTable::factory()->create(), $waiter);
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => $amount]));
    $payment = app(StartPayment::class)->handle($order, $tip);
    app(HandlePaymentWebhook::class)->handle(new PaymentStatusData($payment->provider_invoice_id, PaymentStatus::Success));

    return $payment->refresh();
}

dataset('payment sections', [
    'платежі' => ['/admin/payments', [UserRole::Owner, UserRole::Manager]],
    'журнал вебхуків' => ['/admin/webhook-logs', [UserRole::Owner]],
    'чайові' => ['/admin/tips', [UserRole::Owner, UserRole::Manager, UserRole::Waiter]],
]);

it('відкриває платежі, журнал вебхуків і чайові лише дозволеним ролям', function (string $url, array $allowed) {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get($url);

        if (in_array($role, $allowed, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with('payment sections');

it('показує платежі в списку й картку з історією статусів', function () {
    $payment = paidOrder($this->waiter, 30000, 3000);
    $failed = app(StartPayment::class)->handle(tap(app(OpenOrder::class)->handle($this->table), function (Order $order) {
        app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 10000]));
    }));

    $this->actingAs($this->manager);

    livewire(ListPayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->assertCanNotSeeTableRecords([$failed])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$payment, $failed]);

    $this->get('/admin/payments/'.$payment->id)
        ->assertOk()
        ->assertSee('330 ₴')
        ->assertSee('Ірина Бондар')
        ->assertSee($payment->provider_invoice_id)
        ->assertSee('Історія статусів');

    // Картку платежу не можна відкрити на редагування: такого маршруту немає
    $this->get('/admin/payments/'.$payment->id.'/edit')->assertNotFound();
});

it('менеджер повертає кошти з картки платежу з причиною', function () {
    $payment = paidOrder($this->waiter, 30000, 3000);
    $this->actingAs($this->manager);

    livewire(ViewPayment::class, ['record' => $payment->id])
        ->assertActionVisible('refund')
        ->callAction('refund', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Success);

    livewire(ViewPayment::class, ['record' => $payment->id])
        ->callAction('refund', ['reason' => 'Гість сплатив двічі'])
        ->assertHasNoActionErrors()
        ->assertNotified('Кошти повернено')
        ->assertActionHidden('refund');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(33000)
        ->and($payment->meta['refund']['reason'])->toBe('Гість сплатив двічі')
        ->and($payment->meta['refund']['by'])->toBe($this->manager->id)
        ->and($payment->order->fresh()->amountDue())->toBe(30000);
});

it('оновлює статус платежу в провайдера з картки', function () {
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 10000]));
    $payment = app(StartPayment::class)->handle($order);
    $this->actingAs($this->owner);

    $this->travel(16)->minutes();

    livewire(ViewPayment::class, ['record' => $payment->id])
        ->assertActionHidden('refund')
        ->callAction('sync')
        ->assertNotified('Статус: прострочено');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('для офлайн-платежу немає оновлення статусу, а повернення — лише запис', function () {
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 10000]));
    $payment = app(RecordOfflinePayment::class)->handle($order, PaymentMethod::Cash, $this->waiter);
    $this->actingAs($this->manager);

    livewire(ViewPayment::class, ['record' => $payment->id])
        ->assertActionHidden('sync')
        ->callAction('refund', ['reason' => 'Помилка каси'])
        ->assertNotified('Кошти повернено');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('показує платежі в картці замовлення', function () {
    $payment = paidOrder($this->waiter, 30000, 3000);
    $this->actingAs($this->waiter);

    livewire(ViewOrder::class, ['record' => $payment->order_id])
        ->assertSee('Платежі')
        ->assertSee('Онлайн')
        ->assertSee('30 ₴');
});

it('показує журнал вебхуків власникові', function () {
    $valid = PaymentWebhookLog::create(['provider' => 'monobank', 'provider_invoice_id' => 'p2_ok', 'payload' => ['status' => 'success'], 'signature_valid' => true]);
    $forged = PaymentWebhookLog::create(['provider' => 'monobank', 'provider_invoice_id' => 'p2_bad', 'payload' => ['status' => 'success'], 'signature_valid' => false, 'error' => 'Недійсний підпис']);
    $this->actingAs($this->owner);

    livewire(ListPaymentWebhookLogs::class)
        ->assertCanSeeTableRecords([$valid, $forged])
        ->filterTable('signature_valid', false)
        ->assertCanSeeTableRecords([$forged])
        ->assertCanNotSeeTableRecords([$valid]);
});

it('рахує чайові по офіціантах за період: кількість платежів і середній відсоток', function () {
    $second = User::factory()->waiter()->create(['name' => 'Олег Мельник']);

    $this->travelTo(kyiv('2026-11-10 19:00'));
    paidOrder($this->waiter, 40000, 4000);
    paidOrder($this->waiter, 20000, 0);
    paidOrder($second, 10000, 1500);

    $this->travelTo(kyiv('2026-11-12 23:30'));
    paidOrder($this->waiter, 60000, 9000);
    $refunded = paidOrder($second, 50000, 5000);
    $refunded->forceFill(['status' => PaymentStatus::Refunded])->save();

    $this->travelTo(kyiv('2026-11-16 09:00'));

    $rows = TipsReport::rows(kyiv('2026-11-10 00:00'), kyiv('2026-11-12 00:00'));

    // Платіж без чайових і повернений платіж у звіт не входять
    expect($rows)->toBe([
        ['waiter' => 'Ірина Бондар', 'payments' => 2, 'tips' => 13000, 'base' => 100000, 'percent' => 13.0],
        ['waiter' => 'Олег Мельник', 'payments' => 1, 'tips' => 1500, 'base' => 10000, 'percent' => 15.0],
    ]);

    // Межі періоду — дні в поясі закладу: платіж о 23:30 за Києвом належить 12-му числу
    expect(TipsReport::rows(kyiv('2026-11-10 00:00'), kyiv('2026-11-11 00:00')))->toHaveCount(2)
        ->and(TipsReport::rows(kyiv('2026-11-10 00:00'), kyiv('2026-11-11 00:00'))[0]['tips'])->toBe(4000)
        ->and(TipsReport::rows(kyiv('2026-11-13 00:00'), kyiv('2026-11-16 00:00')))->toBe([])
        ->and(TipsReport::rows(kyiv('2026-11-10 00:00'), kyiv('2026-11-12 00:00'), $second->id))->toHaveCount(1);
});

it('у звіті офіціант бачить лише свої чайові, менеджер — усі', function () {
    $second = User::factory()->waiter()->create(['name' => 'Олег Мельник']);
    paidOrder($this->waiter, 40000, 4000);
    paidOrder($second, 10000, 1500);

    $this->actingAs($this->waiter);

    livewire(TipsReportPage::class)
        ->assertSet('from', '2026-11-01')
        ->assertSet('to', '2026-11-16')
        ->assertSee('Ірина Бондар')
        ->assertSee('40 ₴')
        ->assertDontSee('Олег Мельник')
        ->assertSee('Показано лише ваші чайові');

    $this->actingAs($this->manager);

    livewire(TipsReportPage::class)
        ->assertSee('Ірина Бондар')
        ->assertSee('Олег Мельник')
        ->assertSee('55 ₴')
        ->set('from', '2026-10-01')
        ->set('to', '2026-10-31')
        ->assertSee('За цей період чайових не було')
        // Некоректна дата з адреси не ламає сторінку
        ->set('to', '2026-11-16')
        ->set('from', 'not-a-date')
        ->assertSee('Ірина Бондар');
});

it('планувальник позначає прострочені платежі й чистить старий журнал вебхуків', function () {
    $order = app(OpenOrder::class)->handle($this->table, $this->waiter);
    app(AddOrderItem::class)->handle($order, Dish::factory()->create(['price' => 10000]));
    $payment = app(StartPayment::class)->handle($order);

    // Свіжий платіж планувальник не чіпає
    $this->artisan('payments:sync')->assertSuccessful();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Created);

    $this->travel(16)->minutes();
    $this->artisan('payments:sync')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);

    $old = PaymentWebhookLog::create(['provider' => 'monobank', 'payload' => [], 'signature_valid' => true]);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();
    $recent = PaymentWebhookLog::create(['provider' => 'monobank', 'payload' => [], 'signature_valid' => true]);

    $this->artisan('payments:prune-webhook-logs')->assertSuccessful();

    expect(PaymentWebhookLog::pluck('id')->all())->toBe([$recent->id]);
});
