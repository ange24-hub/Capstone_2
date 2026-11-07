<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Support\RegistryRemarks;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RbiHistoryPreparation
{
    public function prepare(Barangay $area, User $owner, string $month, ?int $actorId): BarangayRbiUpdate
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $month);
        if (! $date || $date->format('Y-m') !== $month) {
            throw new \InvalidArgumentException('Use a valid reporting month.');
        }
        abort_unless($owner->hasRole(User::ROLE_BARANGAY) && $owner->isApproved() && $owner->barangay_id === $area->id, 403);

        return DB::transaction(function () use ($area, $owner, $month, $actorId) {
            Barangay::whereKey($area->id)->lockForUpdate()->firstOrFail();
            $existing = BarangayRbiUpdate::where('barangay_name', $area->name)->whereDate('reporting_month', $month.'-01')->first();
            if ($existing) {
                abort_unless($existing->barangay_user_id === $owner->id, 403, 'An existing monthly form belongs to another preparer. Review it with that preparer.');

                return $existing;
            }
            $records = NewInhabitant::where('barangay_id', $area->id)->whereDate('reporting_month', $month.'-01')->orderBy('source_position')->orderBy('id')->get();
            abort_if($records->isEmpty(), 404);
            $rows = $this->rows($records);
            $report = BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => $area->name,
                'reporting_month' => $month.'-01', 'household_head' => $rows[0]['household_head'] ?? null, 'rows' => $rows, 'deceased_rows' => [],
                'status' => BarangayRbiUpdate::STATUS_DRAFT, 'source_file_name' => 'Prepared from existing RBI Report History']);
            RegistryActivity::create(['barangay_id' => $area->id, 'user_id' => $actorId,
                'description' => 'Prepared '.$month.' RBI classification draft from Report History',
                'changes' => ['record_type' => 'history_arrival_preparation', 'report_id' => $report->id, 'history_record_ids' => $records->modelKeys()]]);

            return $report;
        });
    }

    public function rows(Collection $records): array
    {
        return $records->groupBy('household_number')->flatMap(function ($members): array {
            $head = $members->first(fn (NewInhabitant $member): bool => mb_strtolower(trim((string) $member->relationship_to_head)) === 'head') ?: $members->first();
            $headName = collect([$head->first_name, $head->middle_name, $head->last_name, $head->suffix])->filter()->implode(' ');

            return $members->map(fn (NewInhabitant $member): array => [
                'household_head' => $headName,
                'history_record_id' => $member->id,
                'household_number' => $member->household_number,
                'last_name' => $member->last_name, 'first_name' => $member->first_name, 'middle_name' => $member->middle_name, 'suffix' => $member->suffix,
                'complete_address' => $member->complete_address, 'recorded_age' => $member->recorded_age,
                'education_level' => $member->education_level, 'religion' => $member->religion, 'remarks' => RegistryRemarks::display($member->remarks),
                'inhabitant_name' => collect([$member->last_name, $member->first_name, $member->middle_name, $member->suffix])->filter()->implode(', '),
                'sex' => $member->sex,
                'birth_date' => optional($member->birth_date)->format('Y-m-d') ?: '',
                'birth_place' => $member->birth_place ?: '',
                'civil_status' => $member->civil_status ?: '',
                'occupation' => $member->occupation ?: '',
                'relationship' => $member->relationship_to_head ?: '',
            ])->all();
        })->values()->all();
    }
}
