<?php

use App\Enums\UserRole;
use App\Filament\Pages\BookingSettingsPage;
use App\Filament\Pages\WorkingHoursPage;
use App\Models\SpecialDay;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingSettings;
use App\Services\Payments\TipSettings;

use function Pest\Livewire\livewire;

beforeEach(function () {
    openRestaurant();
    $this->owner = User::factory()->owner()->create();
    $this->manager = User::factory()->manager()->create();
});

// ---------- Години роботи (частина 2, п. 7.9) ----------

it('відкриває години роботи власникові й менеджерові', function () {
    foreach (UserRole::cases() as $role) {
        $response = $this->actingAs(User::factory()->role($role)->create())->get('/admin/working-hours');

        if (in_array($role, [UserRole::Owner, UserRole::Manager], true)) {
            $response->assertOk()->assertSee('Тижневий графік');
        } else {
            $response->assertForbidden();
        }
    }
});

it('менеджер змінює графік, і слоти бронювання одразу це враховують', function () {
    $this->actingAs($this->manager);
    $tuesday = kyiv('2026-11-17 00:00');

    expect(app(AvailabilityService::class)->dayWindow($tuesday))->not->toBeNull();

    livewire(WorkingHoursPage::class)
        ->assertFormSet(['hours.d1.opens_at' => '11:00', 'hours.d1.closes_at' => '23:00', 'hours.d1.is_closed' => false])
        ->fillForm(['hours.d1.opens_at' => '12:00', 'hours.d2.is_closed' => true])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Години роботи збережено');

    expect(substr((string) WorkingHour::firstWhere('weekday', 1)->opens_at, 0, 5))->toBe('12:00')
        ->and(WorkingHour::firstWhere('weekday', 2)->is_closed)->toBeTrue()
        ->and(app(AvailabilityService::class)->dayWindow($tuesday))->toBeNull();
});

it('не зберігає робочий день без годин', function () {
    $this->actingAs($this->manager);

    livewire(WorkingHoursPage::class)
        ->fillForm(['hours.d3.opens_at' => null])
        ->call('save')
        ->assertHasFormErrors(['hours.d3.opens_at']);
});

it('зберігає особливі дні й не чіпає минулих', function () {
    SpecialDay::create(['date' => '2026-11-01', 'is_closed' => true, 'note' => 'Минулий']);
    SpecialDay::create(['date' => '2026-12-25', 'is_closed' => true, 'note' => 'Різдво']);
    $this->actingAs($this->owner);

    livewire(WorkingHoursPage::class)
        ->assertFormSet(fn (array $state) => expect(array_values($state['special_days']))->toHaveCount(1))
        ->set('data.special_days', [
            ['date' => '2026-12-31', 'is_closed' => false, 'opens_at' => '12:00', 'closes_at' => '18:00', 'note' => 'Скорочений день'],
            ['date' => '2027-01-01', 'is_closed' => true, 'opens_at' => null, 'closes_at' => null, 'note' => null],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SpecialDay::orderBy('date')->get()->map(fn (SpecialDay $day) => $day->date->format('Y-m-d'))->all())
        ->toBe(['2026-11-01', '2026-12-31', '2027-01-01']);

    $window = app(AvailabilityService::class)->dayWindow(kyiv('2026-12-31 00:00'));

    expect($window[0]->format('H:i'))->toBe('12:00')
        ->and($window[1]->format('H:i'))->toBe('18:00')
        ->and(app(AvailabilityService::class)->dayWindow(kyiv('2027-01-01 00:00')))->toBeNull();
});

// ---------- Параметри бронювання й чайові (частина 2, пп. 3.1, 5 і 7.9) ----------

it('власник змінює параметри бронювання, не зачіпаючи плату', function () {
    enableBookingFee(15000);
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->assertFormSet(['duration' => 120, 'slot_step' => 30, 'max_guests' => 12])
        ->fillForm(['duration' => 90, 'slot_step' => 15, 'horizon_days' => 14])
        ->callAction('save')
        ->assertHasNoFormErrors();

    expect(BookingSettings::duration())->toBe(90)
        ->and(BookingSettings::slotStep())->toBe(15)
        ->and(BookingSettings::horizonDays())->toBe(14)
        ->and(BookingSettings::buffer())->toBe(15)
        ->and(BookingSettings::fee())->toBe(15000);
});

it('відхиляє параметри бронювання поза межами', function () {
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->fillForm(['duration' => 5, 'max_guests' => 0])
        ->callAction('save')
        ->assertHasFormErrors(['duration', 'max_guests'], 'form');

    expect(BookingSettings::duration())->toBe(120);
});

it('власник налаштовує чайові', function () {
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->assertFormSet(['tip_default_percent' => 10, 'tip_max_percent' => 50, 'tip_show_waiter_name' => true])
        ->fillForm(['tip_percents' => ['20', '0', '7'], 'tip_default_percent' => 7, 'tip_max_percent' => 30, 'tip_show_waiter_name' => false])
        ->callAction('save')
        ->assertHasNoFormErrors();

    expect(TipSettings::percents())->toBe([0, 7, 20])
        ->and(TipSettings::defaultPercent())->toBe(7)
        ->and(TipSettings::maxFor(10000))->toBe(3000)
        ->and(TipSettings::showWaiterName())->toBeFalse();
});

it('не приймає типовий відсоток чайових, якого немає серед варіантів', function () {
    $this->actingAs($this->owner);

    livewire(BookingSettingsPage::class)
        ->fillForm(['tip_percents' => ['0', '5'], 'tip_default_percent' => 10])
        ->callAction('save')
        ->assertHasFormErrors(['tip_default_percent'], 'form');

    expect(TipSettings::percents())->toBe([0, 5, 10, 15]);
});
