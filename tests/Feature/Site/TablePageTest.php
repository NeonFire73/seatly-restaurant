<?php

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\CancelOrderItem;
use App\Actions\Orders\ChangeOrderItemStatus;
use App\Actions\Orders\OpenOrder;
use App\Enums\OrderItemStatus;
use App\Enums\WaiterCallStatus;
use App\Livewire\TablePage;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WaiterCall;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->table = DiningTable::factory()->create(['number' => '12']);
    $this->dish = Dish::factory()->create(['name' => ['uk' => 'Вареники з вишнею', 'en' => 'Cherry varenyky'], 'price' => 16500]);
});

/**
 * Тестовий запит типово каже, що браузер англійський, тож мову задаємо явно.
 */
function visitTable(string $url, string $language = 'uk')
{
    return test()->withHeader('Accept-Language', $language)->get($url);
}

it('відкривається за токеном із QR-коду й закрита від індексації', function () {
    visitTable('/t/'.$this->table->qr_token)
        ->assertOk()
        ->assertSee('Столик № 12')
        ->assertSee('noindex, nofollow', false)
        ->assertSee('Вареники з вишнею')
        ->assertSee(__('table.call.button'));
});

it('не відкривається за невідомим токеном, номером столика чи для вимкненого столика', function () {
    visitTable('/t/'.str_repeat('a', 40))->assertNotFound();
    visitTable('/t/12')->assertNotFound();
    visitTable('/t/'.$this->table->id)->assertNotFound();

    $this->table->update(['is_active' => false]);

    visitTable('/t/'.$this->table->qr_token)->assertNotFound();
});

it('після перевипуску токена старий QR-код перестає працювати', function () {
    $old = $this->table->qr_token;
    $this->table->regenerateQrToken();

    expect($this->table->qr_token)->not->toBe($old);

    visitTable('/t/'.$old)->assertNotFound();
    visitTable('/t/'.$this->table->qr_token)->assertOk();
});

it('уперше відкривається мовою браузера, а далі слухає адресу', function () {
    $url = '/t/'.$this->table->qr_token;

    visitTable($url, 'en-GB,en;q=0.9')->assertRedirect('/en'.$url);
    visitTable('/en'.$url, 'en-GB,en;q=0.9')->assertOk()->assertSee('Table 12')->assertSee('Cherry varenyky');

    // Гість перемкнув мову вручну — назад його не перекидає
    visitTable($url, 'en-GB,en;q=0.9')->assertOk()->assertSee('Столик № 12');
});

it('без замовлення першою показує вкладку меню, а в рахунку — пояснення', function () {
    Livewire::test(TablePage::class, ['token' => $this->table->qr_token])
        ->assertSet('tab', 'menu')
        ->assertSee('Вареники з вишнею')
        ->assertDontSee(__('table.bill.empty_title'))
        ->call('showTab', 'bill')
        ->assertSee(__('table.bill.empty_title'))
        ->assertSee(__('table.bill.empty_text'))
        ->assertDontSee('Вареники з вишнею');
});

it('з відкритим замовленням першою показує рахунок із позиціями й сумою', function () {
    $order = app(OpenOrder::class)->handle($this->table, User::factory()->waiter()->create(['name' => 'Ірина Бондар']));
    $served = app(AddOrderItem::class)->handle($order, $this->dish, 2);
    app(ChangeOrderItemStatus::class)->handle($served, OrderItemStatus::Served);
    $ready = app(AddOrderItem::class)->handle($order, Dish::factory()->create(['name' => ['uk' => 'Узвар'], 'price' => 4000]));
    app(ChangeOrderItemStatus::class)->handle($ready, OrderItemStatus::Ready);
    $cancelled = app(AddOrderItem::class)->handle($order, Dish::factory()->create(['name' => ['uk' => 'Деруни'], 'price' => 9000]));
    app(CancelOrderItem::class)->handle($cancelled, User::factory()->manager()->create());
    $order->forceFill(['paid_total' => 10000])->save();

    Livewire::test(TablePage::class, ['token' => $this->table->qr_token])
        ->assertSet('tab', 'bill')
        ->assertSee('Вареники з вишнею')
        ->assertSee('× 2')
        ->assertSee('330 ₴')
        ->assertSee(__('table.item_status.served'))
        // «Готово» на кухні для гостя все ще «готується»
        ->assertSee(__('table.item_status.cooking'))
        ->assertDontSee('Деруни')
        ->assertSeeInOrder([__('table.bill.subtotal'), '370 ₴', __('table.bill.paid'), '100 ₴', __('table.bill.due'), '270 ₴'])
        ->assertSeeHtml('wire:poll.5s');
});

it('не показує персональних даних гостя й чужих замовлень', function () {
    openRestaurant();
    $reservation = Reservation::factory()->at(kyiv('2026-11-16 12:00'))->create([
        'table_id' => $this->table->id,
        'guest_name' => 'Оксана Гриценко',
    ]);
    $order = app(OpenOrder::class)->handle($this->table, null, $reservation);
    app(AddOrderItem::class)->handle($order, $this->dish);

    $other = app(OpenOrder::class)->handle(DiningTable::factory()->create());
    app(AddOrderItem::class)->handle($other, Dish::factory()->create(['name' => ['uk' => 'Чужа страва']]));

    Livewire::test(TablePage::class, ['token' => $this->table->qr_token])
        ->assertSee('Вареники з вишнею')
        ->assertDontSee('Оксана Гриценко')
        ->assertDontSee($reservation->guest_phone)
        ->assertDontSee($reservation->guest_email)
        ->assertDontSee($reservation->code)
        ->assertDontSee('Чужа страва');
});

it('показує недоступну страву приглушеною з позначкою', function () {
    Dish::factory()->unavailable()->create(['name' => ['uk' => 'Сезонний суп']]);

    Livewire::test(TablePage::class, ['token' => $this->table->qr_token])
        ->assertSee('Сезонний суп')
        ->assertSee(__('site.menu.unavailable'));
});

it('кличе офіціанта не частіше ніж раз на дві хвилини', function () {
    $waiter = User::factory()->waiter()->create();
    $manager = User::factory()->manager()->create();
    $kitchen = User::factory()->kitchen()->create();
    $page = Livewire::test(TablePage::class, ['token' => $this->table->qr_token]);

    $page->call('callWaiter')->assertDispatched('toast', message: __('table.call.sent'));

    expect(WaiterCall::count())->toBe(1)
        ->and(WaiterCall::sole()->status)->toBe(WaiterCallStatus::New)
        ->and(WaiterCall::sole()->table_id)->toBe($this->table->id)
        // Сповіщення в панелі бачать офіціанти й керівництво, кухня — ні
        ->and($waiter->notifications()->count())->toBe(1)
        ->and($manager->notifications()->count())->toBe(1)
        ->and($kitchen->notifications()->count())->toBe(0);

    $page->call('callWaiter')->assertDispatched('toast', message: __('table.call.already'));

    expect(WaiterCall::count())->toBe(1);

    // Інший столик має власний відлік
    Livewire::test(TablePage::class, ['token' => DiningTable::factory()->create()->qr_token])->call('callWaiter');

    expect(WaiterCall::count())->toBe(2);

    $this->travel(121)->seconds();
    $page->call('callWaiter')->assertDispatched('toast', message: __('table.call.sent'));

    expect(WaiterCall::count())->toBe(3);
});

it('не дає підмінити токен і відкидає невідому вкладку', function () {
    $other = DiningTable::factory()->create();
    $page = Livewire::test(TablePage::class, ['token' => $this->table->qr_token]);

    $page->set('tab', 'admin')->assertSet('tab', 'menu');
    $page->call('showTab', 'kitchen')->assertSet('tab', 'menu');

    expect(fn () => $page->set('token', $other->qr_token))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('повідомляє про застарілий код, якщо токен перевипустили при відкритій сторінці', function () {
    $page = Livewire::test(TablePage::class, ['token' => $this->table->qr_token]);

    $this->table->regenerateQrToken();

    $page->call('showTab', 'bill')
        ->assertSee(__('table.stale.title'))
        ->assertDontSee(__('table.call.button'));

    $page->call('callWaiter');

    expect(WaiterCall::count())->toBe(0);
});
