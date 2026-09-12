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
            ->whereYear('movement_date', $year)->whereIn('type', [MigrationRecord::TYPE_IN, MigrationRecord::TYPE_OUT])
            ->get(['barangay_id', 'type', 'movement_date']);
        $byMonth = $records->groupBy(fn ($record) => $record->movement_date->month);
        $months = collect(range(1, 12))->map(fn ($month) => [
            'label' => Carbon::create($year, $month, 1)->format('F'),
            'month' => sprintf('%04d-%02d', $year, $month),
        ] + $this->counts($byMonth->get($month, collect())));
        $byBarangay = $records->groupBy('barangay_id');
        $coverage = $barangays->map(fn ($barangay) => ['name' => $barangay->name]
            + $this->counts($byBarangay->get($barangay->id, collect())));

        return [
            'year' => $year, 'generatedAt' => now(), 'months' => $months, 'coverage' => $coverage,
            'scopeLabel' => $barangayId ? 'Barangay '.$barangays->firstOrFail()->name : 'Municipality of Tomas Oppus',
            'coveredBarangays' => $coverage->where('total', '>', 0)->count(),
            'totalBarangays' => $barangays->count(),
        ] + $this->counts($records);
    }

    private function counts($records): array
    {
        $in = $records->where('type', MigrationRecord::TYPE_IN)->count();
        $out = $records->where('type', MigrationRecord::TYPE_OUT)->count();

        return ['in' => $in, 'out' => $out, 'net' => $in - $out, 'total' => $in + $out];
    }
}
