<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\User;
use App\Services\GisLayers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MapHouseholdController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, Household $household): RedirectResponse
    {
        if ($request->user()->hasRole(User::ROLE_BARANGAY)) {
            abort_unless($request->user()->barangay_id && $household->barangay_id === $request->user()->barangay_id, 403);
        }

        // Editing a map location must not transfer an existing registry household.
        abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $household->barangay_id, 403);
        $request->merge(['barangay_id' => $household->barangay_id]);

        return $this->save($request, $household);
    }

    private function save(Request $request, ?Household $household = null): RedirectResponse
    {
        if ($request->user()->hasRole(User::ROLE_BARANGAY)) {
            abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');
            abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $request->user()->barangay_id, 403);
            $request->merge(['barangay_id' => $request->user()->barangay_id]);
        }

        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'household_name' => ['required', 'string', 'max:255'],
            'household_number' => ['nullable', 'string', 'max:255', Rule::unique('households')->where('barangay_id', $request->input('barangay_id'))->ignore($household?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        if ($request->user()->hasRole(User::ROLE_BARANGAY) && ! app(GisLayers::class)->contains(
            Barangay::findOrFail($validated['barangay_id']), (float) $validated['latitude'], (float) $validated['longitude']
        )) {
            throw ValidationException::withMessages(['latitude' => 'Choose a location inside your assigned barangay boundary.']);
        }
        $validated['household_number'] ??= $household?->household_number ?? 'GIS-'.Str::ulid();
        if ($household) {
            $household->update($validated);
        } else {
            $household = Household::create($validated);
        }

        return redirect()->route('spatial.index', ['barangay_id' => $household->barangay_id])
            ->with('success', 'Household saved. Its location is now on the map.')
            ->with('mapped_household_id', $household->id);
    }
}
