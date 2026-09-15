<?php

namespace App\Services;

use App\Models\{Barangay, BarangayRbiUpdate, MigrationRecord, User};

class ForecastReadiness
{
    public function build(User $user, ?int $barangayId): array
    {
        abort_unless($user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]), 403);
        $secretary = $user->hasRole(User::ROLE_BARANGAY);
        if ($secretary) {
            abort_unless($user->isApproved() && $user->barangay_id, 403);
            abort_if($barangayId && $barangayId !== $user->barangay_id, 403);
            $barangayId = $user->barangay_id;
        }

        // Completed months only. Read aggregate metadata, never resident names or RBI rows.
        $end = now()->startOfMonth();
        $start = $end->copy()->subMonths(24);
        $areas = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get(['id', 'name']);
        $events = MigrationRecord::whereIn('barangay_id', $areas->modelKeys())
            ->where('movement_date', '>=', $start)->where('movement_date', '<', $end)
            ->get(['barangay_id', 'movement_date', 'type']);
        $forms = BarangayRbiUpdate::whereIn('barangay_name', $areas->pluck('name'))
            ->where('status', BarangayRbiUpdate::STATUS_SUBMITTED)
            ->where('reporting_month', '>=', $start)->where('reporting_month', '<', $end)
            ->where(fn ($q) => $q->whereNull('submitted_at')->orWhere('submitted_at', '<=', now()))
            ->when($secretary, fn ($q) => $q->where('barangay_user_id', $user->id))
            ->get(['barangay_name', 'reporting_month']);

        $coverage = $areas->map(function ($area) use ($events, $forms) {
            $movements = $events->where('barangay_id', $area->id);
            $periods = $movements->map(fn ($row) => $row->movement_date->format('Y-m'))->unique()->sort()->values();
            $reportPeriods = $forms->where('barangay_name', $area->name)->map(fn ($row) => $row->reporting_month->format('Y-m'))->unique();
            return ['name' => $area->name, 'events' => $movements->count(), 'months' => $periods->count(),
                'first' => $periods->first(), 'last' => $periods->last(), 'reportMonths' => $reportPeriods->count()];
        });
        $months = collect(range(0, 23))->map(function ($index) use ($start, $events, $forms) {
            $period = $start->copy()->addMonths($index)->format('Y-m');
            $recorded = $events->filter(fn ($row) => $row->movement_date->format('Y-m') === $period);
            return ['period' => $period, 'in' => $recorded->where('type', MigrationRecord::TYPE_IN)->count(),
                'out' => $recorded->where('type', MigrationRecord::TYPE_OUT)->count(),
                'events' => $recorded->count(),
                'forms' => $forms->filter(fn ($row) => $row->reporting_month->format('Y-m') === $period)->count()];
        });

        return compact('secretary', 'barangayId', 'coverage', 'months', 'start', 'end') + [
            'scope' => $barangayId ? ($areas->first()?->name ?? 'Selected barangay') : 'Available municipal records',
            'eventCount' => $events->count(), 'forecastAvailable' => false,
        ];
    }
}
