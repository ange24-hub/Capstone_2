<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\MigrationRecord;
use Carbon\Carbon;

class MigrationSummary
{
    public function build(?int $barangayId, int $year): array
    {
        $barangays = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))
            ->orderBy('name')->get(['id', 'name']);
        $records = MigrationRecord::whereIn('barangay_id', $barangays->modelKeys())
            ->whereYear('movement_date', $year)->where('type', MigrationRecord::TYPE_OUT)
            ->get(['barangay_id', 'type', 'movement_date']);
        $arrivals = app(RbiArrivalReview::class)->history($barangayId, \Illuminate\Support\Carbon::create($year, 12, 1), 12);
        $arrivalMonths = collect($arrivals['rows'])->keyBy('month');
        $arrivalAreas = collect($arrivals['coverage'])->keyBy('id');
        $byMonth = $records->groupBy(fn ($record) => $record->movement_date->month);
        $months = collect(range(1, 12))->map(function ($month) use ($year, $byMonth, $arrivalMonths) {
            $key = sprintf('%04d-%02d', $year, $month);
            $in = $arrivalMonths->get($key)['count'] ?? 0;
            $out = $byMonth->get($month, collect())->count();

            return ['label' => Carbon::create($year, $month, 1)->format('F'), 'month' => $key, 'in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $in + $out];
        });
        $byBarangay = $records->groupBy('barangay_id');
        $coverage = $barangays->map(function ($barangay) use ($arrivalAreas, $byBarangay) {
            $in = $arrivalAreas->get($barangay->id)['events'] ?? 0;
            $out = $byBarangay->get($barangay->id, collect())->count();

            return ['name' => $barangay->name, 'in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $in + $out];
        });
        $in = $months->sum('in');
        $out = $months->sum('out');

        return [
            'year' => $year, 'generatedAt' => now(), 'months' => $months, 'coverage' => $coverage,
            'scopeLabel' => $barangayId ? 'Barangay '.$barangays->firstOrFail()->name : 'Municipality of Tomas Oppus',
            'coveredBarangays' => $coverage->where('total', '>', 0)->count(),
            'totalBarangays' => $barangays->count(),
        ] + ['in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $in + $out];
    }
}
