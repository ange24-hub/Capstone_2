<?php

namespace App\Services;

use App\Models\Inhabitant;
use App\Models\RegistryActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistryMovement
{
    public function version(Inhabitant $resident): string
    {
        $resident = $resident->fresh();

        return hash('sha256', json_encode([$resident->getAttributes(), $resident->migrationRecords()->where('type', 'out')->orderBy('id')->get()->toArray()]));
    }

    public function update(User $actor, Inhabitant $resident, array $data): void
    {
        DB::transaction(function () use ($actor, $resident, $data) {
            // Lock the shared household first so person/household changes cannot race.
            $resident->household()->lockForUpdate()->firstOrFail();
            if ($data['scope'] === 'household' && $resident->fresh()->status !== Inhabitant::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['scope' => 'Open an active registered household member before applying a household change.']);
            }
            $people = Inhabitant::where('barangay_id', $actor->barangay_id)
                ->when($data['scope'] === 'household', fn ($q) => $q->where('household_id', $resident->household_id)->where('status', Inhabitant::STATUS_ACTIVE), fn ($q) => $q->whereKey($resident->id))
                ->orderBy('id')->lockForUpdate()->get();
            if ($people->isEmpty()) {
                throw ValidationException::withMessages(['scope' => 'No eligible residents remain in this household. Refresh the registry.']);
            }
            $versions = $data['versions'];
            if (array_diff($people->modelKeys(), array_keys($versions)) || array_diff(array_keys($versions), $people->modelKeys())) {
                throw ValidationException::withMessages(['versions' => 'The household membership changed. Reopen the form before saving.']);
            }
            foreach ($people as $person) {
                abort_unless(in_array($person->status, [Inhabitant::STATUS_ACTIVE, Inhabitant::STATUS_MIGRATED_OUT], true), 422);
                if (! hash_equals($this->version($person), $versions[$person->id])) {
                    throw ValidationException::withMessages(['versions' => 'A resident or movement record changed. Reopen the form before saving.']);
                }
            }
            foreach ($people as $person) {
                $before = $person->only(['status', 'residence_status']);
                $wasAway = $person->status === Inhabitant::STATUS_MIGRATED_OUT || $person->residence_status === Inhabitant::RESIDENCE_ELSEWHERE;
                $away = in_array($data['action'], ['moved_out', 'transferred'], true);
                $person->update([
                    'status' => $data['action'] === 'transferred' ? Inhabitant::STATUS_MIGRATED_OUT : Inhabitant::STATUS_ACTIVE,
                    'residence_status' => $away ? Inhabitant::RESIDENCE_ELSEWHERE : $data['action'],
                    'residence_source' => 'Confirmed in Resident Registry',
                ]);
                $movementBefore = null;
                $movementAfter = null;
                if ($away) {
                    $event = $wasAway ? $person->migrationRecords()->where('type', 'out')->orderByDesc('movement_date')->orderByDesc('id')->first() : null;
                    $movementBefore = $event?->only(['movement_date', 'destination', 'reason']);
                    $attributes = ['barangay_id' => $person->barangay_id, 'type' => 'out', 'movement_date' => $data['movement_date'],
                        'origin' => $person->barangay->name, 'destination' => $data['destination'] ?? null, 'reason' => $data['reason'] ?? null, 'recorded_by' => $actor->id];
                    if ($event) {
                        $event->update($attributes);
                    } else {
                        $event = $person->migrationRecords()->create($attributes);
                    }
                    $movementAfter = $event->fresh()->only(['movement_date', 'destination', 'reason']);
                }
                RegistryActivity::create(['barangay_id' => $person->barangay_id, 'user_id' => $actor->id, 'inhabitant_id' => $person->id,
                    'description' => 'Registry movement: '.str_replace('_', ' ', $data['action']).' — '.$person->fullName(),
                    'changes' => ['record_type' => 'registry_movement', 'scope' => $data['scope'], 'before' => $before,
                        'after' => $person->only(['status', 'residence_status']), 'movement_before' => $movementBefore, 'movement_after' => $movementAfter]]);
            }
        });
    }
}
