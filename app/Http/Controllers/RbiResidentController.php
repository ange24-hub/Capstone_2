<?php

namespace App\Http\Controllers;

use App\Models\{Inhabitant, User};
use App\Support\HouseholdRbi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RbiResidentController extends Controller
{
    private function authorizeResident(Request $request, Inhabitant $resident): void
    {
        abort_unless($request->user()->hasRole(User::ROLE_BARANGAY) && $request->user()->isApproved()
            && $request->user()->barangay_id === $resident->barangay_id, 403);
        abort_unless($resident->status === Inhabitant::STATUS_ACTIVE, 409, 'This resident is no longer in the consolidated active registry.');
    }

    public function edit(Request $request, Inhabitant $resident)
    {
        $this->authorizeResident($request, $resident);
        $resident->load(['household', 'barangay']);
        return view('rbi-updates.edit-resident', compact('resident'));
    }

    public function update(Request $request, Inhabitant $resident)
    {
        $this->authorizeResident($request, $resident);
        $rules = ['first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'sex' => ['required', 'in:Male,Female'], 'record_version' => ['required', 'string']];
        foreach (array_keys(HouseholdRbi::fields()) as $field) {
            $field = $field === 'relationship' ? 'relationship_to_head' : $field;
            $rules[$field] ??= ['nullable', 'string', 'max:'.($field === 'remarks' ? 5000 : 255)];
        }
        foreach (['family_number', 'individual_number', 'ethnicity', 'contact_number'] as $field) $rules[$field] = ['nullable', 'string', 'max:255'];
        $rules['civil_status'] = ['nullable', 'string', 'max:60'];
        foreach (['family_number', 'individual_number'] as $field) $rules[$field] = ['nullable', 'string', 'max:30'];
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $resident, $data) {
            $locked = Inhabitant::whereKey($resident->id)->lockForUpdate()->firstOrFail();
            $this->authorizeResident($request, $locked);
            if ($data['record_version'] !== (string) $locked->updated_at) {
                throw \Illuminate\Validation\ValidationException::withMessages(['record_version' => 'This record changed after you opened it. Reopen Edit from Consolidated before saving.']);
            }
            unset($data['record_version']);
            $locked->update($data);
        });
        return redirect()->route('barangay.registry.active', ['search' => $resident->fresh()->last_name])
            ->with('status', 'RBI resident changes saved. Consolidated / All Registered is updated automatically.');
    }
}
