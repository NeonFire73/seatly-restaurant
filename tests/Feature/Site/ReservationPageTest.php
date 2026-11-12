<?php

use App\Enums\ReservationStatus;
use App\Livewire\ReservationPage;
use App\Mail\ReservationCancelledMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    openRestaurant();
    Mail::fake();
    $this->reservation = Reservation::factory()->at(kyiv('2026-11-17 18:00'))->create(['guest_name' => 'Оксана Гриценко']);
});

it('показує бронювання за посиланням із токеном і закриває сторінку від індексації', function () {
    $this->get('/booking/'.$this->reservation->token)
        ->assertOk()
        ->assertSee($this->reservation->code)
        ->assertSee('18:00')
        ->assertSee('Оксана Гриценко')
        ->assertSee(__('booking.status.confirmed'))
        ->assertSee('noindex, nofollow', false)
        // Телефон і e-mail гостя на сторінці не показуються
        ->assertDontSee($this->reservation->guest_phone)
        ->assertDontSee($this->reservation->guest_email);

    $this->get('/en/booking/'.$this->reservation->token)->assertOk()->assertSee('Your booking');
});

it('не відкриває бронювання за невідомим токеном, кодом чи ідентифікатором', function () {
    $this->get('/booking/'.str_repeat('a', 48))->assertNotFound();
    $this->get('/booking/'.$this->reservation->code)->assertNotFound();
    $this->get('/booking/'.$this->reservation->id)->assertNotFound();
    $this->get('/booking/'.$this->reservation->token.'/calendar.ics')->assertOk();
    $this->get('/booking/wrong/calendar.ics')->assertNotFound();
});

it('віддає подію календаря файлом', function () {
    $response = $this->get('/booking/'.$this->reservation->token.'/calendar.ics')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($response->headers->get('Content-Disposition'))->toContain('seatly-'.$this->reservation->code.'.ics')
        ->and($response->getContent())->toContain('BEGIN:VEVENT')->toContain('DTSTART:20261117T160000Z');
});

it('гість скасовує бронювання', function () {
    Livewire::test(ReservationPage::class, ['token' => $this->reservation->token])
        ->assertSee(__('booking.page.cancel'))
        ->call('cancel')
        ->assertSee(__('booking.page.cancelled'))
        ->assertSee(__('booking.status.cancelled'))
        ->assertDontSee(__('booking.page.cancel_title'));

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
    Mail::assertQueued(ReservationCancelledMail::class);
});

it('не дає скасувати бронювання після початку візиту чи в кінцевому статусі', function (ReservationStatus $status, string $now) {
    $this->reservation->update(['status' => $status]);
    $this->travelTo(kyiv($now));

    Livewire::test(ReservationPage::class, ['token' => $this->reservation->token])
        ->assertDontSee(__('booking.page.cancel_title'))
        ->call('cancel')
        ->assertSee(__('booking.page.cannot_cancel'));

    expect($this->reservation->fresh()->status)->toBe($status);
    Mail::assertNothingQueued();
})->with([
    'візит уже почався' => [ReservationStatus::Confirmed, '2026-11-17 18:05'],
    'гості за столиком' => [ReservationStatus::Seated, '2026-11-17 17:55'],
    'завершено' => [ReservationStatus::Completed, '2026-11-16 09:00'],
]);

it('не дозволяє підмінити токен у запиті Livewire', function () {
    $other = Reservation::factory()->create();

    Livewire::test(ReservationPage::class, ['token' => $this->reservation->token])
        ->set('token', $other->token);
})->throws(CannotUpdateLockedPropertyException::class);
