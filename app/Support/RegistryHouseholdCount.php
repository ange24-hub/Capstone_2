<?php

namespace App\Support;

use App\Models\Household;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RegistryHouseholdCount
{
    /** Count recorded households represented by the full report, before pagination. */
    public static function forQuery(Builder $residents): int
    {
        return self::recordedHouseholds()
            ->whereIn('id', (clone $residents)->reorder()->select('household_id'))
            ->count();
    }

    public static function forResidents(Collection $residents): int
    {
        return self::recordedHouseholds()
            ->whereIn('id', $residents->pluck('household_id')->filter()->unique()->values())
            ->count();
    }

    private static function recordedHouseholds(): Builder
    {
        return Household::query()
            ->whereRaw("TRIM(household_number) <> ''")
            ->whereRaw('LOWER(TRIM(household_number)) <> ?', ['not recorded']);
    }
}
