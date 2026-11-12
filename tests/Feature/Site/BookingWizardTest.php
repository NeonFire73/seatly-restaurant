<?php

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Livewire\BookingWizard;
use App\Mail\ReservationConfirmedMail;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Reservation;
use App\Services\Booking\TableHold;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->hall = Hall::factory()->create(['name' => ['uk' => 'Основний зал', 'en' => 'Main hall']]);
    $this->table = DiningTable::factory()->for($this->hall)->create(['number' => '7', 'seats' => 4, 'min_guests' => 1]);

    // Майстер, доведений до кроку контактів
    $this->atContacts = fn () => Livewire::test(BookingWizard::class)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '18:00')
        ->call('chooseTable')
        ->call('selectTable', $this->table->id)
        ->call('enterContacts');

    $this->fillContacts = fn ($component) => $component
        ->set('name', 'Оксана Гриценко')
        ->set('phone', '+380 67 123 45 67')
        ->set('email', 'Oksana@Example.com')
        ->set('consent', true);
});

it('відкриває сторінку бронювання обома мовами', function () {
    $this->get('/booking')->assertOk()->assertSee(__('booking.title'))->assertSee(__('booking.when.guests'));
    $this->get('/en/booking')->assertOk()->assertSee('Book a table')->assertSee('Number of guests');
});

it('проводить гостя від дати до екрана успіху', function () {
    $component = ($this->atContacts)()->assertSet('step', 3);

    ($this->fillContacts)($component)
        ->call('review')
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        ->assertSee('Оксана Гриценко')
        ->call('submit')
        ->assertSee(__('booking.done.title'));

    $reservation = Reservation::sole();

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->source)->toBe(ReservationSource::Site)
        ->and($reservation->table_id)->toBe($this->table->id)
        ->and($reservation->guest_phone)->toBe('+380671234567')
        ->and($reservation->guest_email)->toBe('oksana@example.com')
        ->and($reservation->starts_at->format('Y-m-d H:i'))->toBe('2026-11-17 16:00')
        ->and($reservation->locale)->toBe('uk');

    $component->assertSee($reservation->code);
    Mail::assertQueued(ReservationConfirmedMail::class);

    // Після бронювання утримання столика знято
    expect(app(TableHold::class)->of($component->get('holdOwner')))->toBeNull();
});

it('не пускає далі без дати й часу', function () {
    Livewire::test(BookingWizard::class)
        ->call('chooseTable')
        ->assertSet('step', 1)
        ->assertSee(__('booking.errors.pick_slot'));
});

it('не приймає дату чи час, яких немає серед доступних', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectDate', '2026-11-15')
        ->assertSet('date', null)
        ->call('selectDate', 'not-a-date')
        ->assertSet('date', null)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '03:00')
        ->assertSet('time', null)
        ->call('selectTime', '18:10')
        ->assertSet('time', null);
});

it('обмежує кількість гостей найбільшим столиком', function () {
    Livewire::test(BookingWizard::class)
        ->set('guests', 4)
        ->call('incrementGuests')
        ->assertSet('guests', 4)
        ->call('decrementGuests')
        ->assertSet('guests', 3);
});

it('показує зайнятий слот неактивним', function () {
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    Livewire::test(BookingWizard::class)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '18:00')
        ->assertSet('time', null)
        ->call('selectTime', '13:00')
        ->assertSet('time', '13:00');
});

it('не дає обрати зайнятий чи замалий столик', function () {
    $small = DiningTable::factory()->for($this->hall)->create(['seats' => 2, 'min_guests' => 1]);
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();
    DiningTable::factory()->for($this->hall)->create(['seats' => 6, 'min_guests' => 1]);

    Livewire::test(BookingWizard::class)
        ->set('guests', 3)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '18:00')
        ->call('chooseTable')
        ->assertSet('step', 2)
        ->call('selectTable', $this->table->id)
        ->assertSet('tableId', null)
        ->call('selectTable', $small->id)
        ->assertSet('tableId', null)
        ->call('enterContacts')
        ->assertSet('step', 2)
        ->assertSee(__('booking.errors.pick_table'));
});

it('обирає столик автоматично — найменший, що підходить', function () {
    $small = DiningTable::factory()->for($this->hall)->create(['seats' => 2, 'min_guests' => 1]);

    Livewire::test(BookingWizard::class)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '18:00')
        ->call('chooseTable')
        ->call('autoSelect')
        ->assertSet('tableId', $small->id);
});

it('утримує обраний столик, і інший гість бачить його зайнятим', function () {
    $first = ($this->atContacts)();

    expect($first->get('holdExpiresAt'))->toBe(now()->addMinutes(10)->getTimestamp());

    // Другий гість — інший браузер, тобто інша сесія
    session()->flush();

    Livewire::test(BookingWizard::class)
        ->call('selectDate', '2026-11-17')
        ->call('selectTime', '18:00')
        // Єдиний столик утримується — слот для другого гостя недоступний
        ->assertSet('time', null);
});

it('повертає до вибору столика, коли час утримання минув', function () {
    $component = ($this->atContacts)();

    $this->travel(11)->minutes();

    $component->call('holdExpired')
        ->assertSet('step', 2)
        ->assertSet('tableId', null)
        ->assertSee(__('booking.table.hold_expired'));
});

it('перевіряє контакти й згоду з умовами', function () {
    ($this->atContacts)()
        ->set('name', 'О')
        ->set('phone', '12345')
        ->set('email', 'not-an-email')
        ->call('review')
        ->assertHasErrors(['name', 'phone', 'email', 'consent'])
        ->assertSet('step', 3)
        ->assertSee(__('booking.contacts.phone_invalid'))
        ->assertSee(__('booking.contacts.consent_required'));
});

it('повертає на крок столика зі збереженими даними, якщо столик встигли зайняти', function () {
    $component = ($this->fillContacts)(($this->atContacts)())->call('review')->assertSet('step', 4);

    // Поки гість перевіряв дані, менеджер забронював цей столик телефоном
    Reservation::factory()->for($this->table, 'table')->at(kyiv('2026-11-17 18:00'))->create();

    $component->call('submit')
        ->assertSet('step', 2)
        ->assertSet('tableId', null)
        ->assertSet('name', 'Оксана Гриценко')
        ->assertSee(__('booking.errors.table_taken'));

    expect(Reservation::count())->toBe(1);
});

it('зберігає стан майстра після оновлення сторінки', function () {
    ($this->atContacts)()->set('name', 'Оксана');

    Livewire::test(BookingWizard::class)
        ->assertSet('step', 3)
        ->assertSet('date', '2026-11-17')
        ->assertSet('time', '18:00')
        ->assertSet('tableId', $this->table->id)
        ->assertSet('name', 'Оксана');
});

it('скидає застарілий стан із сесії', function () {
    ($this->atContacts)();

    // Гість повернувся наступного вечора: обраний час уже минув
    $this->travelTo(kyiv('2026-11-17 19:00'));

    Livewire::test(BookingWizard::class)
        ->assertSet('step', 1)
        ->assertSet('time', null)
        ->assertSet('tableId', null);
});

it('обмежує частоту бронювань з однієї адреси', function () {
    foreach (range(0, 4) as $i) {
        DiningTable::factory()->for($this->hall)->create(['seats' => 4, 'min_guests' => 1]);
    }

    $book = function () {
        $component = Livewire::test(BookingWizard::class)
            ->call('selectDate', '2026-11-17')
            ->call('selectTime', '18:00')
            ->call('chooseTable')
            ->call('autoSelect')
            ->call('enterContacts');

        return ($this->fillContacts)($component)->call('review')->call('submit');
    };

    foreach (range(1, 5) as $i) {
        $book()->call('startOver');
    }

    $book()->assertSee(__('booking.errors.too_many'));

    expect(Reservation::count())->toBe(5);
});

it('бронює англійською й запам\'ятовує мову для листів', function () {
    app()->setLocale('en');
    $component = ($this->atContacts)();
    app()->setLocale('uk');

    ($this->fillContacts)($component)->call('review')->call('submit')->assertSee('Your table is booked');

    expect(Reservation::sole()->locale)->toBe('en');
});

it('нормалізує телефон', function (string $input, ?string $expected) {
    expect(BookingWizard::normalizePhone($input))->toBe($expected);
})->with([
    ['+380 67 123 45 67', '+380671234567'],
    ['0671234567', '+380671234567'],
    ['380671234567', '+380671234567'],
    ['+48 601 234 567', null],
    ['067123456', null],
    ['', null],
]);
