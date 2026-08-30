<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistryController extends Controller
{
    public function index(Request $request): View
    {
        $isBarangaySecretary = $request->user()->hasRole(User::ROLE_BARANGAY);

        if ($isBarangaySecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');
        }

        $query = Inhabitant::with(['barangay', 'household', 'migrationRecords'])
            ->latest();

        if ($isBarangaySecretary) {
            $query->where('barangay_id', $request->user()->barangay_id);
        }

        if (! $isBarangaySecretary && $request->filled('barangay_id')) {
            $query->where('barangay_id', $request->integer('barangay_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereHas('household', fn ($household) => $household
                        ->where('household_number', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%'));
            });
        }

        return view('registry.index', [
            'inhabitants' => $query->paginate(15)->withQueryString(),
            'barangays' => $isBarangaySecretary
                ? Barangay::whereKey($request->user()->barangay_id)->get()
                : Barangay::orderBy('name')->get(),
            'statusLabels' => Inhabitant::statusLabels(),
            'migrationTypes' => MigrationRecord::typeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $barangay = $this->resolveBarangay($request, $validated);
        $household = $this->resolveHousehold($barangay, $validated);

        $inhabitant = Inhabitant::create($this->inhabitantAttributes($validated, $barangay, $household));
        $this->recordMigrationIfPresent($request, $inhabitant, $validated);

        return redirect()->route('registry.index')->with('status', 'Inhabitant record created.');
    }

    public function edit(Request $request, Inhabitant $inhabitant): View
    {
        $this->authorizeInhabitant($request, $inhabitant);

        return view('registry.edit', [
            'inhabitant' => $inhabitant->load(['barangay', 'household', 'migrationRecords']),
            'barangays' => Barangay::orderBy('name')->get(),
            'statusLabels' => Inhabitant::statusLabels(),
            'migrationTypes' => MigrationRecord::typeLabels(),
        ]);
    }

    public function update(Request $request, Inhabitant $inhabitant): RedirectResponse
    {
        $this->authorizeInhabitant($request, $inhabitant);

        $validated = $this->validatePayload($request);
        $barangay = $this->resolveBarangay($request, $validated);
        $household = $this->resolveHousehold($barangay, $validated);

        $inhabitant->update($this->inhabitantAttributes($validated, $barangay, $household));
        $this->recordMigrationIfPresent($request, $inhabitant, $validated);

        return redirect()->route('registry.index')->with('status', 'Inhabitant record updated.');
    }

    public function destroy(Request $request, Inhabitant $inhabitant): RedirectResponse
    {
        $this->authorizeInhabitant($request, $inhabitant);
        $inhabitant->delete();

        return redirect()->route('registry.index')->with('status', 'Inhabitant record deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'barangay_name' => ['required_without:barangay_id', 'nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'household_number' => ['required', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'resident_user_id' => ['nullable', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'relationship_to_head' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:60'],
            'religion' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(array_keys(Inhabitant::statusLabels()))],
            'migration_type' => ['nullable', Rule::in(array_keys(MigrationRecord::typeLabels()))],
            'movement_date' => ['nullable', 'required_with:migration_type', 'date'],
            'origin' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function resolveBarangay(Request $request, array $validated): Barangay
    {
        $barangay = filled($validated['barangay_id'] ?? null)
            ? Barangay::findOrFail($validated['barangay_id'])
            : Barangay::firstOrCreate(
                ['name' => $validated['barangay_name']],
                ['municipality' => $validated['municipality'] ?? null]
            );

        if ($request->user()->hasRole(User::ROLE_BARANGAY)) {
            abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');
            abort_unless($request->user()->barangay_id === $barangay->id, 403);
        }

        return $barangay;
    }

    private function resolveHousehold(Barangay $barangay, array $validated): Household
    {
        return Household::updateOrCreate(
            [
                'barangay_id' => $barangay->id,
                'household_number' => $validated['household_number'],
            ],
            [
                'purok' => $validated['purok'] ?? null,
                'address' => $validated['address'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]
        );
    }

    private function inhabitantAttributes(array $validated, Barangay $barangay, Household $household): array
    {
        return collect($validated)
            ->only([
                'resident_user_id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'relationship_to_head',
                'sex',
                'birth_date',
                'birth_place',
                'civil_status',
                'religion',
                'occupation',
                'education_level',
                'contact_number',
                'status',
            ])
            ->merge([
                'barangay_id' => $barangay->id,
                'household_id' => $household->id,
            ])
            ->all();
    }

    private function recordMigrationIfPresent(Request $request, Inhabitant $inhabitant, array $validated): void
    {
        if (blank($validated['migration_type'] ?? null)) {
            return;
        }

        MigrationRecord::create([
            'inhabitant_id' => $inhabitant->id,
            'barangay_id' => $inhabitant->barangay_id,
            'type' => $validated['migration_type'],
            'movement_date' => $validated['movement_date'],
            'origin' => $validated['origin'] ?? null,
            'destination' => $validated['destination'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);
    }

    private function authorizeInhabitant(Request $request, Inhabitant $inhabitant): void
    {
        if ($request->user()->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            return;
        }

        abort_unless($request->user()->barangay_id === $inhabitant->barangay_id, 403);
    }
}
