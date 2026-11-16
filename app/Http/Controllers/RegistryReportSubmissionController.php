<?php

namespace App\Http\Controllers;

use App\Models\Inhabitant;
use App\Models\RegistryReportSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistryReportSubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['scope' => ['required', 'in:registered,living_here,living_elsewhere,unconfirmed']]);
        $barangay = $request->user()->barangay;
        abort_unless($barangay, 403);

        $query = Inhabitant::withoutMigrationDemo()
            ->with('household:id,household_number,purok')
            ->where('barangay_id', $barangay->id)
            ->where('status', Inhabitant::STATUS_ACTIVE)
            ->when($data['scope'] !== 'registered', fn ($q) => $q->where('residence_status', $data['scope']))
            ->orderBy('household_id')->orderBy('id');

        $rows = $query->get()->map(fn (Inhabitant $resident): array => [
            'household_number' => $resident->household?->household_number,
            'family_number' => $resident->family_number,
            'last_name' => $resident->last_name,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'suffix' => $resident->suffix,
            'relationship_to_head' => $resident->relationship_to_head,
            'purok' => $resident->household?->purok,
            'birth_place' => $resident->birth_place,
            'birth_date' => $resident->birth_date?->format('Y-m-d'),
            'recorded_age' => $resident->recorded_age,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'education_level' => $resident->education_level,
            'religion' => $resident->religion,
            'occupation' => $resident->occupation,
            'remarks' => \App\Support\RegistryRemarks::display($resident->remarks),
            'ethnicity' => $resident->ethnicity,
            'residence_status' => $resident->residenceLabel(),
        ])->all();

        if ($rows === []) {
            return back()->withErrors(['scope' => 'There are no active registry records in this scope to submit.']);
        }

        $submittedAt = now();
        $snapshotJson = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $submission = DB::transaction(fn () => RegistryReportSubmission::create([
            'barangay_id' => $barangay->id,
            'submitted_by' => $request->user()->id,
            'scope' => $data['scope'],
            'barangay_name' => $barangay->name,
            'as_of' => $submittedAt,
            'record_count' => count($rows),
            'snapshot' => $rows,
            'snapshot_hash' => hash('sha256', $snapshotJson),
            'submitted_at' => $submittedAt,
        ]));

        return back()->with('status', $submission->reference().' submitted to Municipal DILG/Admin with '.$submission->record_count.' registry records. The submitted snapshot will not change when the live registry is edited.');
    }

    public function index()
    {
        $submissions = RegistryReportSubmission::with(['barangay', 'submitter'])
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20);

        return view('municipal.registry-submissions.index', compact('submissions'));
    }

    public function show(RegistryReportSubmission $submission)
    {
        return view('municipal.registry-submissions.show', compact('submission'));
    }

    public function download(RegistryReportSubmission $submission): StreamedResponse
    {
        $columns = [
            'household_number' => 'HH No.', 'family_number' => 'Family No.', 'last_name' => 'Last Name',
            'first_name' => 'First Name', 'middle_name' => 'Middle Name', 'suffix' => 'Qualifier',
            'relationship_to_head' => 'Relationship to Household Head', 'purok' => 'Purok / Sitio',
            'birth_place' => 'Place of Birth', 'birth_date' => 'Birth (mm-dd-yy)', 'recorded_age' => 'Age',
            'sex' => 'Sex (M/F)', 'civil_status' => 'Civil Status', 'education_level' => 'Grade/Year/Level Completed',
            'religion' => 'Religion', 'occupation' => 'Occupation', 'remarks' => 'Other Info', 'ethnicity' => 'Ethnicity',
        ];
        $headers = array_values($columns);
        $filename = $submission->reference().'-'.str_replace(' ', '-', $submission->barangay_name).'.csv';

        return response()->streamDownload(function () use ($submission, $headers, $columns): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers, ',', '"', '');
            foreach ($submission->snapshot ?? [] as $row) {
                $values = array_map(fn ($key) => $row[$key] ?? '', array_keys($columns));
                if (!empty($row['birth_date'])) {
                    $dateIndex = array_search('birth_date', array_keys($columns), true);
                    $values[$dateIndex] = \Carbon\Carbon::parse($row['birth_date'])->format('m-d-y');
                }
                if (isset($row['sex'])) {
                    $sexIndex = array_search('sex', array_keys($columns), true);
                    $values[$sexIndex] = ['Male' => 'M', 'Female' => 'F'][$row['sex']] ?? $row['sex'];
                }
                $values = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $values);
                fputcsv($file, $values, ',', '"', '');
            }
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
