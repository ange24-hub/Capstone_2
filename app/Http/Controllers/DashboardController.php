<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\DocumentRequest;
use App\Models\Household;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        if (! $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU) && ! $request->user()->isApproved()) {
            return redirect()->route('approval.pending');
        }

        return match ($request->user()->role) {
            User::ROLE_MUNICIPAL_LGU => redirect()->route('dashboard.municipal'),
            User::ROLE_BARANGAY => redirect()->route('dashboard.barangay'),
            default => redirect()->route('dashboard.resident'),
        };
    }

    public function municipal(): View
    {
        $rbiUpdates = BarangayRbiUpdate::with('barangayUser')
            ->where('status', BarangayRbiUpdate::STATUS_SUBMITTED)
            ->latest('submitted_at')
            ->get();

        $updatesByBarangay = $rbiUpdates->groupBy('barangay_name');
        $currentPeriodStart = now()->subMonthsNoOverflow(6)->startOfMonth();
        $previousPeriodStart = now()->subMonthsNoOverflow(12)->startOfMonth();
        $migrationRecordsByBarangay = MigrationRecord::whereDate('movement_date', '>=', $previousPeriodStart)
            ->get()
            ->groupBy('barangay_id');
        $secretaryApprovalRequests = User::with('barangay')
            ->where('role', User::ROLE_BARANGAY)
            ->where('approval_status', User::APPROVAL_PENDING)
            ->oldest()
            ->get();
        $barangayOrder = array_flip(Barangay::TOMAS_OPPUS_BARANGAYS);
        $barangays = Barangay::where('municipality', Barangay::MUNICIPALITY)
            ->withCount(['inhabitants', 'households', 'migrationRecords', 'secretaries'])
            ->get()
            ->sortBy(fn (Barangay $barangay): int => $barangayOrder[$barangay->name] ?? PHP_INT_MAX)
            ->values()
            ->each(function (Barangay $barangay) use ($currentPeriodStart, $migrationRecordsByBarangay, $previousPeriodStart, $updatesByBarangay): void {
                $updates = $updatesByBarangay->get($barangay->name, collect());
                $migrationRecords = $migrationRecordsByBarangay->get($barangay->id, collect());
                $currentMovements = $migrationRecords->filter(
                    fn (MigrationRecord $record): bool => $record->movement_date->gte($currentPeriodStart)
                );
                $previousMovements = $migrationRecords->filter(
                    fn (MigrationRecord $record): bool => $record->movement_date->gte($previousPeriodStart)
                        && $record->movement_date->lt($currentPeriodStart)
                );
                $monthlyRbiUpdates = $updates
                    ->groupBy(fn (BarangayRbiUpdate $update): string => optional($update->reporting_month)->format('Y-m') ?: 'undated')
                    ->sortKeysDesc();
                $recentRbiNetChanges = $monthlyRbiUpdates->take(3)->map(
                    fn ($familyForms): int => $familyForms->sum(
                        fn (BarangayRbiUpdate $update): int => count($update->rows ?? []) - count($update->deceased_rows ?? [])
                    )
                );
                $latestMonthForms = $monthlyRbiUpdates->first() ?: collect();
                $latestUpdate = $updates->first();

                $barangay->setAttribute('submitted_updates_count', $updates->count());
                $barangay->setAttribute('registry_rows_count', $updates->sum(
                    fn ($update) => count($update->rows ?? []) + count($update->deceased_rows ?? [])
                ));
                $barangay->setAttribute('latest_submission_at', optional($latestUpdate)->submitted_at);
                $barangay->setAttribute('latest_rbi_net_change', $latestMonthForms->sum(
                    fn (BarangayRbiUpdate $update): int => count($update->rows ?? []) - count($update->deceased_rows ?? [])
                ));
                $barangay->setAttribute('predicted_monthly_change', $recentRbiNetChanges->isNotEmpty()
                    ? (int) round($recentRbiNetChanges->average())
                    : 0);
                $barangay->setAttribute('migration_in_6m', $currentMovements->where('type', MigrationRecord::TYPE_IN)->count());
                $barangay->setAttribute('migration_out_6m', $currentMovements->where('type', MigrationRecord::TYPE_OUT)->count());
                $barangay->setAttribute('movement_total_6m', $currentMovements->count());
                $barangay->setAttribute('previous_movement_total_6m', $previousMovements->count());
            });

        $highMovementThreshold = max(3, (int) ceil(($barangays->avg('movement_total_6m') ?: 0) * 1.5));

        $barangays->each(function (Barangay $barangay) use ($highMovementThreshold): void {
            $currentMovements = $barangay->movement_total_6m;
            $previousMovements = $barangay->previous_movement_total_6m;
            $movementChangePercent = $previousMovements > 0
                ? (int) round((($currentMovements - $previousMovements) / $previousMovements) * 100)
                : ($currentMovements > 0 ? 100 : 0);
            $netMigration = $barangay->migration_in_6m - $barangay->migration_out_6m;

            if ($currentMovements >= $highMovementThreshold) {
                $level = 'high';
                $signal = 'High movement: review service demand, housing, and local capacity.';
            } elseif ($barangay->predicted_monthly_change < 0 || $netMigration < 0) {
                $level = 'watch';
                $signal = 'Decline watch: validate out-migration causes and vulnerable households.';
            } else {
                $level = 'stable';
                $signal = 'Stable indicator: maintain monitoring and routine resource allocation.';
            }

            $barangay->setAttribute('net_migration_6m', $netMigration);
            $barangay->setAttribute('movement_change_percent', $movementChangePercent);
            $barangay->setAttribute('movement_level', $level);
            $barangay->setAttribute('planning_signal', $signal);
        });

        $analyticsSummary = [
            'migration_in_6m' => $barangays->sum('migration_in_6m'),
            'migration_out_6m' => $barangays->sum('migration_out_6m'),
            'net_migration_6m' => $barangays->sum('net_migration_6m'),
            'predicted_monthly_change' => $barangays->sum('predicted_monthly_change'),
            'high_movement_count' => $barangays->where('movement_level', 'high')->count(),
            'high_movement_threshold' => $highMovementThreshold,
        ];

        return view('dashboards.municipal', [
            'rbiUpdates' => $rbiUpdates,
            'barangays' => $barangays,
            'secretaryApprovalRequests' => $secretaryApprovalRequests,
            'analyticsSummary' => $analyticsSummary,
        ]);
    }

    public function municipalBarangays(): View
    {
        $dashboard = $this->municipal();

        return view('municipal.barangays.index', $dashboard->getData());
    }

    public function barangay(Request $request): View
    {
        $barangay = auth()->user()->barangay;

        if ($barangay) {
            $barangay->loadCount(['inhabitants', 'households', 'migrationRecords']);
        }

        $residentApprovalRequests = $barangay
            ? User::with('barangay')
                ->where('role', User::ROLE_RESIDENT)
                ->where('barangay_id', $barangay->id)
                ->where('approval_status', User::APPROVAL_PENDING)
                ->oldest()
                ->get()
            : collect();

        $barangayDocumentRequests = $barangay
            ? DocumentRequest::with(['user', 'barangay'])
                ->where('barangay_id', $barangay->id)
                ->latest()
                ->get()
            : collect();

        $rbiUpdates = BarangayRbiUpdate::where('barangay_user_id', auth()->id())
            ->latest()
            ->get();
        $draftRbiUpdate = $request->integer('edit')
            ? $rbiUpdates->first(fn (BarangayRbiUpdate $update): bool => $update->id === $request->integer('edit'))
            : ($request->routeIs('barangay.rbi-updates.index') && ! $request->boolean('new')
                ? ($rbiUpdates->firstWhere('status', BarangayRbiUpdate::STATUS_DRAFT)
                    ?: $rbiUpdates->first(fn (BarangayRbiUpdate $update): bool => optional($update->reporting_month)->format('Y-m') === now()->format('Y-m')))
                : null);

        $rbiHouseholds = $barangay
            ? Household::with(['inhabitants' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name')])
                ->where('barangay_id', $barangay->id)
                ->orderBy('household_number')
                ->get()
                ->map(function (Household $household): array {
                    $head = $household->inhabitants->first(fn ($inhabitant): bool => in_array(
                        mb_strtolower(trim((string) $inhabitant->relationship_to_head)),
                        ['head', 'household head', 'self'],
                        true
                    )) ?: $household->inhabitants->first();

                    return [
                        'id' => $household->id,
                        'label' => $head?->fullName() ?: 'Household '.$household->household_number,
                        'household_number' => $household->household_number,
                    ];
                })
                ->values()
            : collect();

        return view($request->routeIs('barangay.rbi-updates.index') ? 'rbi-updates.index' : 'dashboards.barangay', [
            'rbiUpdates' => $rbiUpdates,
            'barangay' => $barangay,
            'residentApprovalRequests' => $residentApprovalRequests,
            'barangayDocumentRequests' => $barangayDocumentRequests,
            'documentRequestStatuses' => DocumentRequest::statusLabels(),
            'draftRbiUpdate' => $draftRbiUpdate,
            'rbiRowFields' => BarangayRbiUpdate::rowFields(),
            'rbiDeceasedRowFields' => BarangayRbiUpdate::deceasedRowFields(),
            'rbiHouseholds' => $rbiHouseholds,
        ]);
    }

    public function resident(): View
    {
        return view('dashboards.resident', [
            'documentTypes' => DocumentRequest::typeLabels(),
            'documentRequests' => auth()->user()->documentRequests()->with('barangay')->latest()->get(),
        ]);
    }
}
