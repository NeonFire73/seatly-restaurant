<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = LocalTime::now()->addDay()->setTime(18, 0)->utc();

        return [
            'table_id' => DiningTable::factory(),
            'guest_name' => fake()->name(),
            'guest_phone' => '+38067'.fake()->numerify('#######'),
            'guest_email' => fake()->safeEmail(),
            'guests_count' => 2,
            'starts_at' => $start,
            'ends_at' => $start->addHours(2),
            'status' => ReservationStatus::Confirmed,
            'locale' => 'uk',
            'source' => ReservationSource::Site,
            'fee_amount' => 0,
        ];
    }

    /**
     * Початок у поясі закладу чи будь-якому іншому; тривалість — у хвилинах.
     */
    public function at(CarbonInterface $startsAt, int $minutes = 120): static
    {
        $start = LocalTime::of($startsAt)->utc();

        return $this->state(fn () => [
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($minutes),
        ]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
