<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MigrationDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $records = MigrationRecord::with(['barangay', 'inhabitant.household'])
            ->when($request->user()->hasRole(User::ROLE_BARANGAY) && $request->user()->barangay_id, fn ($query) => $query
                ->where('barangay_id', $request->user()->barangay_id))
            ->when($request->filled('barangay_id'), fn ($query) => $query
                ->where('barangay_id', $request->integer('barangay_id')))
            ->latest('movement_date')
            ->get();

        $barangayStats = $records
            ->groupBy('barangay_id')
            ->map(function ($items) {
                $in = $items->where('type', MigrationRecord::TYPE_IN)->count();
                $out = $items->where('type', MigrationRecord::TYPE_OUT)->count();

                return [
                    'barangay' => $items->first()->barangay,
                    'in' => $in,
                    'out' => $out,
                    'net' => $in - $out,
                    'total' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $monthlyTrend = $records
            ->groupBy(fn (MigrationRecord $record): string => $record->movement_date->format('Y-m'))
            ->map(fn ($items, string $month): array => [
                'month' => $month,
                'in' => $items->where('type', MigrationRecord::TYPE_IN)->count(),
                'out' => $items->where('type', MigrationRecord::TYPE_OUT)->count(),
            ])
            ->sortBy('month')
            ->values();

        return view('dashboards.migration', [
            'barangays' => Barangay::orderBy('name')->get(),
            'records' => $records->take(12),
            'barangayStats' => $barangayStats,
            'monthlyTrend' => $monthlyTrend,
            'totalInhabitants' => Inhabitant::when($request->user()->hasRole(User::ROLE_BARANGAY) && $request->user()->barangay_id, fn ($query) => $query
                ->where('barangay_id', $request->user()->barangay_id))
                ->count(),
            'totalIn' => $records->where('type', MigrationRecord::TYPE_IN)->count(),
            'totalOut' => $records->where('type', MigrationRecord::TYPE_OUT)->count(),
        ]);
    }
}
