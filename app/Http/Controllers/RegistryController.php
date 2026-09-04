<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\NewInhabitant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class RegistryController extends Controller
{
    public function activeRegistry(Request $request): View
    {
        abort_unless($request->user()->barangay, 403, 'Your secretary account is not assigned to a barangay.');

        $request->merge([
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => null,
        ]);

        return $this->index($request);
    }

    public function newInhabitants(Request $request): View
    {
        abort_unless($request->user()->barangay, 403, 'Your secretary account is not assigned to a barangay.');

        $request->merge([
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
        ]);

        return $this->index($request);
    }

    public function deceasedRecords(Request $request): View
    {
        abort_unless($request->user()->barangay, 403, 'Your secretary account is not assigned to a barangay.');

        $request->merge([
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'deceased',
        ]);

        return $this->index($request);
    }

    public function index(Request $request): View
    {
        $isBarangaySecretary = $request->user()->hasRole(User::ROLE_BARANGAY);

        if ($isBarangaySecretary) {
            abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');
        }

        $query = Inhabitant::with(['barangay', 'household', 'migrationRecords'])
            ->orderBy('household_id')
            ->orderBy('id');

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
            'inhabitants' => $query->paginate(25)->withQueryString(),
            'registryBarangay' => $isBarangaySecretary
                ? $request->user()->barangay
                : ($request->filled('barangay_id') ? Barangay::find($request->integer('barangay_id')) : null),
            'barangays' => $isBarangaySecretary
                ? Barangay::whereKey($request->user()->barangay_id)->get()
                : Barangay::orderBy('name')->get(),
            'statusLabels' => Inhabitant::statusLabels(),
            'migrationTypes' => MigrationRecord::typeLabels(),
            'deceasedRecords' => DeceasedInhabitant::where('barangay_id', $request->user()->barangay_id)
                ->orderBy('source_position')
                ->get(),
            'newInhabitantRecords' => NewInhabitant::where('barangay_id', $request->user()->barangay_id)
                ->when($request->filled('reporting_month'), fn ($query) => $query->whereDate('reporting_month', $request->string('reporting_month')->toString().'-01'))
                ->orderByDesc('reporting_month')
                ->orderBy('source_position')
                ->get(),
            'activeHouseholdOptions' => Household::with(['inhabitants' => fn ($query) => $query->orderBy('id')])
                ->where('barangay_id', $request->user()->barangay_id)
                ->orderBy('household_number')
                ->get()
                ->map(function (Household $household): array {
                    $head = $household->inhabitants->first(fn (Inhabitant $inhabitant): bool => in_array(
                        mb_strtolower(trim((string) $inhabitant->relationship_to_head)),
                        ['head', 'household head', 'self'],
                        true
                    )) ?: $household->inhabitants->first();

                    return [
                        'id' => $household->id,
                        'number' => $household->household_number,
                        'head' => $head ? $head->last_name.', '.$head->first_name.' '.$head->middle_name : 'Household '.$household->household_number,
                    ];
                }),
            'registryHouseholdCount' => Household::where('barangay_id', $request->user()->barangay_id)->count(),
        ]);
    }

    public function updateDeceased(Request $request, DeceasedInhabitant $deceasedInhabitant): RedirectResponse
    {
        abort_unless($request->user()->barangay_id === $deceasedInhabitant->barangay_id, 403);

        $validated = $request->validate([
            'household_number' => ['required', 'string', 'max:100'],
            'family_number' => ['nullable', 'string', 'max:30'],
            'individual_number' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'relationship_to_head' => ['nullable', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'sex' => ['nullable', Rule::in(['Male', 'Female'])],
            'civil_status' => ['nullable', 'string', 'max:60'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'death_date' => ['nullable', 'date'],
        ]);

        $deceasedInhabitant->update($validated);

        return redirect()->route('registry.index', ['source' => strtoupper($deceasedInhabitant->barangay->name).'.xlsx', 'sheet' => 'deceased'])
            ->with('status', 'Deceased record updated.');
    }

    public function updateNewInhabitant(Request $request, NewInhabitant $newInhabitant): RedirectResponse
    {
        abort_unless($request->user()->barangay_id === $newInhabitant->barangay_id, 403);

        $validated = $this->validateNewInhabitant($request);

        $this->reopenNewInhabitantReport($newInhabitant);
        $newInhabitant->update($validated);

        return redirect()->route('registry.index', ['source' => strtoupper($newInhabitant->barangay->name).'.xlsx', 'sheet' => 'new-inhabitants'])
            ->with('status', 'New inhabitant record updated.');
    }

    public function editNewInhabitant(Request $request, NewInhabitant $newInhabitant): View
    {
        abort_unless($request->user()->barangay_id === $newInhabitant->barangay_id, 403);
        return view('registry.edit-new-inhabitant', ['record' => $newInhabitant]);
    }

    public function destroyNewInhabitant(Request $request, NewInhabitant $newInhabitant): RedirectResponse
    {
        abort_unless($request->user()->barangay_id === $newInhabitant->barangay_id, 403);
        $month = optional($newInhabitant->reporting_month)->format('Y-m');
        $this->reopenNewInhabitantReport($newInhabitant);
        $newInhabitant->delete();

        return redirect()->route('registry.index', array_filter([
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
            'reporting_month' => $month,
        ]))->with('status', 'New inhabitant member deleted.');
    }

    public function downloadNewMonthlyReportPdf(Request $request, string $month): Response
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);
        $records = NewInhabitant::where('barangay_id', $request->user()->barangay_id)
            ->whereDate('reporting_month', $month.'-01')->orderBy('source_position')->get();
        abort_if($records->isEmpty(), 404);

        return Pdf::loadView('registry.new-inhabitants-pdf', [
            'barangay' => $request->user()->barangay,
            'month' => $month,
            'families' => $records->groupBy('household_number'),
        ])->setPaper('a4', 'landscape')->download('New_Inhabitants_'.strtoupper($request->user()->barangay->name).'_'.$month.'.pdf');
    }

    public function submitNewMonthlyReport(Request $request, string $month): RedirectResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);
        $records = NewInhabitant::where('barangay_id', $request->user()->barangay_id)
            ->whereDate('reporting_month', $month.'-01')->orderBy('source_position')->get();
        abort_if($records->isEmpty(), 404);

        $rows = $records->groupBy('household_number')->flatMap(function ($members): array {
            $head = $members->first(fn (NewInhabitant $member): bool => mb_strtolower(trim((string) $member->relationship_to_head)) === 'head') ?: $members->first();
            $headName = collect([$head->first_name, $head->middle_name, $head->last_name, $head->suffix])->filter()->implode(' ');
            return $members->map(fn (NewInhabitant $member): array => [
                'household_head' => $headName,
                'inhabitant_name' => collect([$member->last_name, $member->first_name, $member->middle_name, $member->suffix])->filter()->implode(', '),
                'sex' => $member->sex,
                'birth_date' => optional($member->birth_date)->format('Y-m-d') ?: '',
                'birth_place' => $member->birth_place ?: '',
                'civil_status' => $member->civil_status ?: '',
                'occupation' => $member->occupation ?: '',
                'relationship' => $member->relationship_to_head ?: '',
            ])->all();
        })->values()->all();

        DB::transaction(function () use ($request, $month, $records, $rows): void {
            $report = BarangayRbiUpdate::firstOrNew(['barangay_user_id' => $request->user()->id, 'reporting_month' => $month.'-01']);
            $report->fill([
                'barangay_name' => $request->user()->barangay->name,
                'household_head' => $rows[0]['household_head'] ?? null,
                'as_of_date' => now()->toDateString(),
                'prepared_by' => $request->user()->barangay->secretary_name ?: $request->user()->name,
                'attested_by' => $request->user()->barangay->punong_barangay_name,
                'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
                'rows' => $rows,
                'deceased_rows' => [],
                'submitted_at' => now(),
            ])->save();
            $report->deceasedRecords()->delete();
            $report->rbiFamilies()->delete();
            foreach ($records->groupBy('household_number')->values() as $position => $members) {
                $headMember = $members->first(fn (NewInhabitant $member): bool => mb_strtolower(trim((string) $member->relationship_to_head)) === 'head') ?: $members->first();
                $headName = collect([$headMember->first_name, $headMember->middle_name, $headMember->last_name, $headMember->suffix])->filter()->implode(' ');
                $family = $report->rbiFamilies()->create(['household_head' => $headName, 'position' => $position]);
                foreach ($members->values() as $memberPosition => $member) {
                    $family->members()->create(['inhabitant_name' => collect([$member->last_name,$member->first_name,$member->middle_name,$member->suffix])->filter()->implode(', '), 'sex'=>$member->sex, 'birth_date'=>$member->birth_date, 'birth_place'=>$member->birth_place, 'civil_status'=>$member->civil_status, 'occupation'=>$member->occupation, 'relationship'=>$member->relationship_to_head, 'position'=>$memberPosition]);
                }
            }
            NewInhabitant::whereKey($records->modelKeys())->update(['submitted_rbi_update_id' => $report->id]);
        });

        return redirect()->route('registry.index', ['source'=>strtoupper($request->user()->barangay->name).'.xlsx','sheet'=>'new-inhabitants','reporting_month'=>$month])->with('status', 'Monthly New Inhabitants report submitted to Municipal DILG.');
    }

    private function reopenNewInhabitantReport(NewInhabitant $record): void
    {
        if (! $record->submitted_rbi_update_id) return;
        $report = BarangayRbiUpdate::find($record->submitted_rbi_update_id);
        $report?->update(['status' => BarangayRbiUpdate::STATUS_DRAFT, 'submitted_at' => null]);
        NewInhabitant::where('submitted_rbi_update_id', $record->submitted_rbi_update_id)->update(['submitted_rbi_update_id' => null]);
    }

    public function storeNewInhabitant(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        $validated = $this->validateNewInhabitant($request);
        NewInhabitant::create($validated + [
            'barangay_id' => $request->user()->barangay_id,
            'source_position' => NewInhabitant::where('barangay_id', $request->user()->barangay_id)->max('source_position') + 1,
        ]);

        return redirect()->route('registry.index', ['source' => strtoupper($request->user()->barangay->name).'.xlsx', 'sheet' => 'new-inhabitants'])
            ->with('status', 'New inhabitant record added.');
    }

    public function storeNewInhabitantFamily(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        $validated = $request->validate([
            'reporting_month' => ['required', 'date_format:Y-m'],
            'existing_household_id' => ['nullable', Rule::exists('households', 'id')->where('barangay_id', $request->user()->barangay_id)],
            'household_number' => ['nullable', 'required_without:existing_household_id', 'string', 'max:100'],
            'members' => ['required', 'array'],
            'members.*.last_name' => ['nullable', 'string', 'max:255'],
            'members.*.first_name' => ['nullable', 'string', 'max:255'],
            'members.*.middle_name' => ['nullable', 'string', 'max:255'],
            'members.*.suffix' => ['nullable', 'string', 'max:30'],
            'members.*.relationship_to_head' => ['nullable', 'string', 'max:255'],
            'members.*.complete_address' => ['nullable', 'string', 'max:255'],
            'members.*.birth_place' => ['nullable', 'string', 'max:255'],
            'members.*.birth_date' => ['nullable', 'date'],
            'members.*.recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'members.*.sex' => ['nullable', Rule::in(['Male', 'Female'])],
            'members.*.civil_status' => ['nullable', 'string', 'max:60'],
            'members.*.education_level' => ['nullable', 'string', 'max:255'],
            'members.*.religion' => ['nullable', 'string', 'max:255'],
            'members.*.occupation' => ['nullable', 'string', 'max:255'],
            'members.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $members = collect($validated['members'])
            ->filter(fn (array $member): bool => filled($member['last_name'] ?? null) || filled($member['first_name'] ?? null))
            ->values();

        if ($members->isEmpty() || $members->contains(fn (array $member): bool => blank($member['last_name'] ?? null) || blank($member['first_name'] ?? null) || blank($member['sex'] ?? null))) {
            return back()->withErrors(['members' => 'Every entered family member needs a first name, last name, and sex.'])->withInput();
        }

        $householdNumber = filled($validated['existing_household_id'] ?? null)
            ? Household::where('barangay_id', $request->user()->barangay_id)->findOrFail($validated['existing_household_id'])->household_number
            : trim((string) $validated['household_number']);

        DB::transaction(function () use ($request, $validated, $members, $householdNumber): void {
            $position = (int) NewInhabitant::where('barangay_id', $request->user()->barangay_id)->max('source_position');
            foreach ($members as $member) {
                NewInhabitant::create($member + [
                    'barangay_id' => $request->user()->barangay_id,
                    'reporting_month' => $validated['reporting_month'].'-01',
                    'household_number' => $householdNumber,
                    'month_submitted' => strtoupper(date('F Y', strtotime($validated['reporting_month'].'-01'))),
                    'source_position' => ++$position,
                ]);
            }
        });

        return redirect()->route('registry.index', [
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
            'reporting_month' => $validated['reporting_month'],
        ])->with('status', 'Family saved and added to the consolidated monthly New Inhabitants record.');
    }

    public function storeNewInhabitantMonthlyReport(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        $validated = $request->validate([
            'reporting_month' => ['required', 'date_format:Y-m'],
            'families' => ['required', 'array', 'min:1'],
            'families.*.existing_household_id' => ['nullable', Rule::exists('households', 'id')->where('barangay_id', $request->user()->barangay_id)],
            'families.*.household_number' => ['nullable', 'string', 'max:100'],
            'families.*.members' => ['required', 'array'],
            'families.*.members.*.last_name' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.first_name' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.middle_name' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.suffix' => ['nullable', 'string', 'max:30'],
            'families.*.members.*.relationship_to_head' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.complete_address' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.birth_place' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.birth_date' => ['nullable', 'date'],
            'families.*.members.*.recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'families.*.members.*.sex' => ['nullable', Rule::in(['Male', 'Female'])],
            'families.*.members.*.civil_status' => ['nullable', 'string', 'max:60'],
            'families.*.members.*.education_level' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.religion' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.occupation' => ['nullable', 'string', 'max:255'],
            'families.*.members.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $families = collect($validated['families'])->map(function (array $family) use ($request): array {
            $members = collect($family['members'])
                ->filter(fn (array $member): bool => filled($member['last_name'] ?? null) || filled($member['first_name'] ?? null))
                ->values();
            $householdNumber = filled($family['existing_household_id'] ?? null)
                ? Household::where('barangay_id', $request->user()->barangay_id)->findOrFail($family['existing_household_id'])->household_number
                : trim((string) ($family['household_number'] ?? ''));

            return ['household_number' => $householdNumber, 'members' => $members];
        })->filter(fn (array $family): bool => $family['members']->isNotEmpty())->values();

        if ($families->isEmpty()
            || $families->contains(fn (array $family): bool => $family['household_number'] === '')
            || $families->contains(fn (array $family): bool => $family['members']->contains(
                fn (array $member): bool => blank($member['last_name'] ?? null) || blank($member['first_name'] ?? null) || blank($member['sex'] ?? null)
            ))) {
            return back()->withErrors(['families' => 'Each family needs a household selection or number, and every entered member needs a first name, last name, and sex.'])->withInput();
        }

        DB::transaction(function () use ($request, $validated, $families): void {
            $position = (int) NewInhabitant::where('barangay_id', $request->user()->barangay_id)->max('source_position');
            foreach ($families as $family) {
                foreach ($family['members'] as $member) {
                    NewInhabitant::create($member + [
                        'barangay_id' => $request->user()->barangay_id,
                        'reporting_month' => $validated['reporting_month'].'-01',
                        'household_number' => $family['household_number'],
                        'month_submitted' => strtoupper(date('F Y', strtotime($validated['reporting_month'].'-01'))),
                        'source_position' => ++$position,
                    ]);
                }
            }
        });

        return redirect()->route('registry.index', [
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
            'reporting_month' => $validated['reporting_month'],
        ])->with('status', $families->count().' family forms saved as one consolidated monthly report.');
    }

    public function addNewFamilyToActive(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        $validated = $request->validate([
            'household_number' => ['required', 'string', 'max:100'],
            'reporting_month' => ['required', 'date_format:Y-m'],
        ]);

        $reportingDate = $validated['reporting_month'].'-01';
        $records = NewInhabitant::where('barangay_id', $request->user()->barangay_id)
            ->where('household_number', $validated['household_number'])
            ->whereDate('reporting_month', $reportingDate)
            ->orderBy('source_position')
            ->get();

        abort_if($records->isEmpty(), 404);

        if ($records->contains(fn (NewInhabitant $record): bool => blank($record->sex))) {
            return back()->withErrors(['family' => 'Set the sex of every family member before adding the family to Active Household.']);
        }

        DB::transaction(function () use ($request, $records, $validated): void {
            $household = Household::firstOrCreate(
                ['barangay_id' => $request->user()->barangay_id, 'household_number' => $validated['household_number']],
                ['purok' => $records->first()->purok, 'address' => $records->first()->complete_address]
            );
            $nextFamily = (int) Inhabitant::where('barangay_id', $request->user()->barangay_id)->max('family_number') + 1;
            $nextIndividual = (int) Inhabitant::where('barangay_id', $request->user()->barangay_id)->max('individual_number');

            foreach ($records as $index => $record) {
                if ($record->active_inhabitant_id) {
                    continue;
                }
                $active = Inhabitant::create([
                    'barangay_id' => $request->user()->barangay_id,
                    'household_id' => $household->id,
                    'registry_sequence' => $index === 0 ? $validated['household_number'] : null,
                    'family_number' => (string) $nextFamily,
                    'individual_number' => (string) ++$nextIndividual,
                    'last_name' => $record->last_name,
                    'first_name' => $record->first_name,
                    'middle_name' => $record->middle_name,
                    'suffix' => $record->suffix,
                    'relationship_to_head' => $record->relationship_to_head,
                    'birth_place' => $record->birth_place,
                    'birth_date' => $record->birth_date,
                    'recorded_age' => $record->recorded_age,
                    'sex' => $record->sex,
                    'civil_status' => $record->civil_status,
                    'education_level' => $record->education_level,
                    'religion' => $record->religion,
                    'occupation' => $record->occupation,
                    'remarks' => $record->remarks,
                    'status' => Inhabitant::STATUS_ACTIVE,
                ]);
                $record->update(['active_inhabitant_id' => $active->id, 'added_to_active_at' => now()]);
            }
        });

        return redirect()->route('registry.index', [
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
            'reporting_month' => $validated['reporting_month'],
        ])->with('status', 'Family added to Active Household successfully.');
    }

    public function removeNewFamilyFromActive(Request $request): RedirectResponse
    {
        abort_unless($request->user()->barangay_id, 403);
        $validated = $request->validate([
            'household_number' => ['required', 'string', 'max:100'],
            'reporting_month' => ['required', 'date_format:Y-m'],
        ]);

        $records = NewInhabitant::where('barangay_id', $request->user()->barangay_id)
            ->where('household_number', $validated['household_number'])
            ->whereDate('reporting_month', $validated['reporting_month'].'-01')
            ->get();

        abort_if($records->isEmpty(), 404);

        DB::transaction(function () use ($request, $records, $validated): void {
            $activeIds = $records->pluck('active_inhabitant_id')->filter()->values();
            $records->each->update(['active_inhabitant_id' => null, 'added_to_active_at' => null]);
            Inhabitant::where('barangay_id', $request->user()->barangay_id)->whereKey($activeIds)->delete();

            $household = Household::where('barangay_id', $request->user()->barangay_id)
                ->where('household_number', $validated['household_number'])
                ->first();
            if ($household && ! $household->inhabitants()->exists()) {
                $household->delete();
            }
        });

        return redirect()->route('registry.index', [
            'source' => strtoupper($request->user()->barangay->name).'.xlsx',
            'sheet' => 'new-inhabitants',
            'reporting_month' => $validated['reporting_month'],
        ])->with('status', 'Family removed from Active Household. The monthly New Inhabitants record was kept.');
    }

    private function validateNewInhabitant(Request $request): array
    {
        return $request->validate([
            'household_number' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'relationship_to_head' => ['nullable', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:255'],
            'complete_address' => ['nullable', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'sex' => ['nullable', Rule::in(['Male', 'Female'])],
            'civil_status' => ['nullable', 'string', 'max:60'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'month_submitted' => ['nullable', 'string', 'max:255'],
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

        return redirect()->route('registry.index', $request->filled('source') ? ['source' => $request->string('source')->toString()] : [])
            ->with('status', 'Inhabitant record updated.');
    }

    public function destroy(Request $request, Inhabitant $inhabitant): RedirectResponse
    {
        $this->authorizeInhabitant($request, $inhabitant);
        $inhabitant->delete();

        return redirect()->route('registry.index', $request->filled('source') ? ['source' => $request->string('source')->toString()] : [])
            ->with('status', 'Inhabitant record deleted.');
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
            'registry_sequence' => ['nullable', 'string', 'max:30'],
            'family_number' => ['nullable', 'string', 'max:30'],
            'individual_number' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'relationship_to_head' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'birth_date' => ['nullable', 'date'],
            'recorded_age' => ['nullable', 'integer', 'between:0,150'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:60'],
            'religion' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
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
                'registry_sequence',
                'family_number',
                'individual_number',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'relationship_to_head',
                'sex',
                'birth_date',
                'recorded_age',
                'birth_place',
                'civil_status',
                'religion',
                'occupation',
                'education_level',
                'contact_number',
                'remarks',
                'ethnicity',
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
