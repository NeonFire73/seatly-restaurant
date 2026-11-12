<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\Ics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Сторінка бронювання гостя. Доступ — лише за токеном із листа; невідомий токен дає 404.
 */
class ReservationController extends Controller
{
    public function show(string $token): View
    {
        return view('site.reservation', ['reservation' => $this->find($token)]);
    }

    public function calendar(string $token): Response
    {
        $reservation = $this->find($token);

        return response(Ics::forReservation($reservation), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="seatly-'.$reservation->code.'.ics"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function find(string $token): Reservation
    {
        $reservation = Reservation::findByToken($token);

        abort_if($reservation === null, 404);

        return $reservation;
    }
}
