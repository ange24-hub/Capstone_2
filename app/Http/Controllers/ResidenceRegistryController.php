<?php

namespace App\Http\Controllers;

use App\Models\Inhabitant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidenceRegistryController extends Controller
{
    public function index(Request $request)
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay && $barangay->usesResidenceRegistry(), 403);
        $scope = $request->query('scope', 'living_here');
        abort_unless(in_array($scope, ['living_here', 'living_elsewhere', 'unconfirmed'], true), 422);
        $query = Inhabitant::with('household')->where('barangay_id', $barangay->id)
            ->where('status', Inhabitant::STATUS_ACTIVE)->where('residence_status', $scope);
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
        }
        return view('registry.residence', [
            'barangay'=>$barangay, 'scope'=>$scope,
            'residents'=>$query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(25)->withQueryString(),
            'counts'=>Inhabitant::where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)
                ->selectRaw('residence_status, COUNT(*) as total')->groupBy('residence_status')->pluck('total', 'residence_status'),
        ]);
    }

    public function update(Request $request, Inhabitant $inhabitant)
    {
        abort_unless($request->user()->barangay_id === $inhabitant->barangay_id, 403);
        abort_unless($request->user()->barangay->usesResidenceRegistry(), 403);
        $transfer = $request->input('residence_status') === 'transferred';
        $validated = $request->validate([
            'residence_status' => ['required', Rule::in([...array_keys(Inhabitant::residenceLabels()), 'transferred'])],
            'transfer_destination' => [Rule::requiredIf($transfer), 'nullable', 'string', 'max:255'],
            'transfer_confirmed' => $transfer ? ['required', 'accepted'] : ['nullable'],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $inhabitant, $validated, $transfer) {
            $resident = Inhabitant::whereKey($inhabitant->id)->lockForUpdate()->firstOrFail();
            abort_unless($resident->status === Inhabitant::STATUS_ACTIVE, 422, 'This resident is no longer active. Refresh the list.');
            $before = $resident->only(['status', 'residence_status']);
            $resident->residence_status = $transfer ? Inhabitant::RESIDENCE_ELSEWHERE : $validated['residence_status'];
            if ($transfer) $resident->status = Inhabitant::STATUS_MIGRATED_OUT;
            $resident->residence_source = 'Confirmed by barangay staff';
            $resident->save();
            if ($transfer) \App\Models\MigrationRecord::create([
                'inhabitant_id' => $resident->id, 'barangay_id' => $resident->barangay_id, 'type' => 'out',
                'movement_date' => now()->toDateString(), 'destination' => $validated['transfer_destination'], 'recorded_by' => $request->user()->id,
            ]);
            $changes = [];
            foreach ($before as $field => $value) if ($value !== $resident->$field) $changes[$field] = ['before' => $value, 'after' => $resident->$field];
            if ($changes) \App\Models\RegistryActivity::create([
                'barangay_id' => $resident->barangay_id, 'user_id' => $request->user()->id, 'inhabitant_id' => $resident->id,
                'description' => ($transfer ? 'Transferred resident to '.$validated['transfer_destination'].': ' : 'Updated residence: ').$resident->fullName(),
                'changes' => $changes,
            ]);
        });
        return back()->with('status', $transfer ? 'Resident transferred to Moved Out and removed from Consolidated RBI.' : 'Current residence updated.');
    }

    public function download(Request $request)
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay && $barangay->usesResidenceRegistry(), 403);
        $scope = $request->query('scope', 'living_here');
        abort_unless(in_array($scope, ['registered', 'living_here', 'living_elsewhere', 'unconfirmed'], true), 422);
        $query = Inhabitant::with('household')->where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)
            ->when($scope !== 'registered', fn ($q) => $q->where('residence_status', $scope))
            ->orderBy('household_id')->orderBy('id');
        return response()->streamDownload(function () use ($query, $barangay) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Barangay','Household','Last name','First name','Middle name','Qualifier','Sex','Birth date','Current residence','Remarks'], ',', '"', '');
            foreach ($query->lazy(250) as $resident) {
                $values = [$barangay->name, $resident->household->household_number, $resident->last_name, $resident->first_name,
                    $resident->middle_name, $resident->suffix, $resident->sex, $resident->birth_date?->format('Y-m-d'), $resident->residenceLabel(), \App\Support\RegistryRemarks::display($resident->remarks)];
                // Keep spreadsheet formulas from being executed when users open the CSV.
                $values = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $values);
                fputcsv($file, $values, ',', '"', '');
            }
            fclose($file);
        }, strtoupper($barangay->name).'-'.$scope.'.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
