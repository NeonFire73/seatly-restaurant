<?php

use Carbon\CarbonImmutable;
use Database\Seeders\WorkingHourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Заклад працює за демо-графіком (пн–чт 11:00–23:00), а «зараз» — понеділок, 16.11.2026, 09:00 за Києвом.
 */
function openRestaurant(): void
{
    test()->seed(WorkingHourSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-16 09:00', 'Europe/Kyiv'));
}

/**
 * Момент у поясі закладу: kyiv('2026-11-16 18:00').
 */
function kyiv(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'Europe/Kyiv');
}
