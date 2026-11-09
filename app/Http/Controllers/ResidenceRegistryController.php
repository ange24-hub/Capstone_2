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
        $query = Inhabitant::withoutMigrationDemo()->with('household')->where('barangay_id', $barangay->id)
            ->where('status', Inhabitant::STATUS_ACTIVE)->where('residence_status', $scope);
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
        }
        return view('registry.residence', [
            'barangay'=>$barangay, 'scope'=>$scope,
            'residents'=>$query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(25)->withQueryString(),
            'counts'=>Inhabitant::withoutMigrationDemo()->where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)
                ->selectRaw('residence_status, COUNT(*) as total')->groupBy('residence_status')->pluck('total', 'residence_status'),
        ]);
    }

    public function update(Request $request, Inhabitant $inhabitant)
    {
        abort_unless($request->user()->barangay_id === $inhabitant->barangay_id, 403);
        abort_unless($request->user()->barangay->usesResidenceRegistry(), 403);
        return redirect()->route('registry.movement', $inhabitant)->withErrors(['movement'=>'Review and save residence changes in Resident Registry.']);
    }

    public function download(Request $request)
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay && $barangay->usesResidenceRegistry(), 403);
        $scope = $request->query('scope', 'living_here');
        abort_unless(in_array($scope, ['registered', 'living_here', 'living_elsewhere', 'unconfirmed'], true), 422);
        $query = Inhabitant::withoutMigrationDemo()->with('household')->where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)
            ->when($scope !== 'registered', fn ($q) => $q->where('residence_status', $scope))
            ->orderBy('household_id')->orderBy('id');
        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            // Match the columns and order of the printable consolidated registry.
            fputcsv($file, [
                'HH NO.', 'FAMILY NO.', 'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'QUALIFIER',
                'RELATIONSHIP TO HH HEAD', 'PUROK / SITIO', 'PLACE OF BIRTH', 'DATE OF BIRTH',
                'AGE', 'SEX (M/F)', 'CIVIL STATUS', 'SCHOOL LEVEL', 'RELIGION', 'OCCUPATION',
                'REMARKS', 'ETHNICITY',
            ], ',', '"', '');
            foreach ($query->lazy(250) as $resident) {
                $values = [
                    $resident->household?->household_number, $resident->family_number,
                    $resident->last_name, $resident->first_name, $resident->middle_name, $resident->suffix,
                    $resident->relationship_to_head, $resident->household?->purok, $resident->birth_place,
                    $resident->birth_date?->format('m-d-y'), $resident->recorded_age,
                    ['Male' => 'M', 'Female' => 'F'][$resident->sex] ?? $resident->sex,
                    $resident->civil_status, $resident->education_level, $resident->religion,
                    $resident->occupation, \App\Support\RegistryRemarks::display($resident->remarks),
                    $resident->ethnicity,
                ];
                // Keep spreadsheet formulas from being executed when users open the CSV.
                $values = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $values);
                fputcsv($file, $values, ',', '"', '');
            }
            fclose($file);
        }, strtoupper($barangay->name).'-'.$scope.'.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
