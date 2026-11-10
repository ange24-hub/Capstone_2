<?php

namespace App\Services;

use App\Models\Barangay;

class MigrationSummary
{
    public function build(?int $barangayId, int $year): array
    {
        $barangays = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))
            ->orderBy('name')->get(['id', 'name']);
        $history = app(RecordedMigrationHistory::class)->build($barangayId, $year);
        $months = $history['months'];
        $coverage = $history['coverage'];
        $in = $months->sum('in');
        $out = $months->sum('out');

        return [
            'year' => $year, 'generatedAt' => now(config('migration_forecast.timezone', 'Asia/Manila')), 'months' => $months, 'coverage' => $coverage,
            'scopeLabel' => $barangayId ? 'Barangay '.$barangays->firstOrFail()->name : 'Municipality of Tomas Oppus',
            'in_known' => $history['in_known'], 'out_known' => $history['out_known'], 'duplicates' => $history['duplicates'],
            'coveredBarangays' => $coverage->where('total', '>', 0)->count(),
            'totalBarangays' => $barangays->count(),
        ] + ['in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $in + $out];
    }
}
