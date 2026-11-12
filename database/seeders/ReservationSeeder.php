<?php

namespace Database\Seeders;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Демо-бронювання: кілька минулих днів (для історії) і найближчий тиждень (частина 2, п. 10.3).
 * Записи створюються напряму, без дії CreateReservation: демо-гостям не треба надсилати листи,
 * а минулі бронювання дія не пропустила б.
 */
class ReservationSeeder extends Seeder
{
    // [номер столика, час, гостей, ім'я, джерело]
    private const PLAN = [
        ['1', '12:00', 2, 'Оксана Гриценко', ReservationSource::Site],
        ['3', '13:00', 4, 'Тарас Савченко', ReservationSource::Phone],
        ['6', '18:00', 5, 'Марія Литвин', ReservationSource::Site],
        ['8', '19:00', 8, 'Дмитро Кравець', ReservationSource::Phone],
        ['4', '19:30', 3, 'Anna Schmidt', ReservationSource::Site],
        ['13', '18:30', 4, 'Юлія Романюк', ReservationSource::Site],
        ['2', '20:00', 2, 'Богдан Олійник', ReservationSource::Admin],
        ['15', '20:00', 6, 'James Carter', ReservationSource::Site],
    ];

    public function run(AvailabilityService $availability): void
    {
        $tables = DiningTable::all()->keyBy('number');
        $manager = User::where('email', 'like', 'manager@%')->first();
        $today = LocalTime::now()->startOfDay();

        foreach (range(-3, 7) as $offset) {
            $day = $today->addDays($offset);

            if ($availability->dayWindow($day) === null) {
                continue;
            }

            foreach (self::PLAN as $index => [$number, $time, $guests, $name, $source]) {
                $table = $tables->get($number);

                // Щодня інший набір бронювань, щоб розклад не виглядав однаковим
                if ($table === null || ($index + $offset) % 3 === 0) {
                    continue;
                }

                $start = LocalTime::parse($day->format('Y-m-d'), $time);

                if ($start === null) {
                    continue;
                }

                $locale = 'uk';

                if (preg_match('/^[A-Za-z ]+$/', $name) === 1) {
                    $locale = 'en';
                }

                $createdBy = null;

                if ($source !== ReservationSource::Site) {
                    $createdBy = $manager?->id;
                }

                $status = $this->statusFor($start->isPast(), $index, $offset);

                Reservation::create([
                    'table_id' => $table->id,
                    'guest_name' => $name,
                    'guest_phone' => sprintf('+38067%07d', 1000000 + ($offset + 3) * 100 + $index),
                    'guest_email' => 'guest'.($index + 1).'@'.config('seatly.demo.email_domain'),
                    'guests_count' => $guests,
                    'starts_at' => $start->utc(),
                    'ends_at' => $availability->endsAt($start)->utc(),
                    'status' => $status,
                    'locale' => $locale,
                    'source' => $source,
                    'fee_amount' => 0,
                    'cancelled_at' => $this->cancelledAt($status, $start),
                    'seated_at' => $this->seatedAt($status, $start),
                    'created_by' => $createdBy,
                ]);
            }
        }
    }

    private function statusFor(bool $isPast, int $index, int $offset): ReservationStatus
    {
        if (! $isPast) {
            if (($index + $offset) % 7 === 5) {
                return ReservationStatus::Cancelled;
            }

            return ReservationStatus::Confirmed;
        }

        return match (($index + $offset + 3) % 6) {
            4 => ReservationStatus::NoShow,
            5 => ReservationStatus::Cancelled,
            default => ReservationStatus::Completed,
        };
    }

    private function cancelledAt(ReservationStatus $status, CarbonImmutable $start): ?CarbonImmutable
    {
        if ($status !== ReservationStatus::Cancelled) {
            return null;
        }

        return $start->subHours(5)->utc();
    }

    private function seatedAt(ReservationStatus $status, CarbonImmutable $start): ?CarbonImmutable
    {
        if ($status !== ReservationStatus::Completed) {
            return null;
        }

        return $start->addMinutes(5)->utc();
    }
}
