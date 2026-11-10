<?php

namespace App\Http\Controllers;

use App\Models\{Barangay, PopulationReportSubmission, RegistryActivity, User};
use App\Services\{PopulationReportSubmissionSnapshot, PopulationSpreadsheet, PopulationSummary};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PopulationReportSubmissionController extends Controller
{
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->isApproved() && $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]), 403);
        if ($user->hasRole(User::ROLE_BARANGAY)) abort_unless($user->barangay_id, 403);
        abort_unless(Schema::hasTable('population_report_submissions'), 503, 'Population submission storage is not ready.');
    }

    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $filters = $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'report_year' => ['nullable', 'integer', 'between:1900,'.now()->year], 'semester' => ['nullable', Rule::in([1, 2])]]);
        $isSecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        if ($isSecretary && $request->filled('barangay_id')) abort_unless($request->integer('barangay_id') === (int) $request->user()->barangay_id, 403);
        $areaId = $isSecretary ? $request->user()->barangay_id : ($request->integer('barangay_id') ?: null);
        $submissions = PopulationReportSubmission::query()->when($areaId, fn ($q) => $q->where('barangay_id', $areaId))
            ->when($request->filled('report_year'), fn ($q) => $q->where('report_year', $request->integer('report_year')))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->integer('semester')))
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $barangays = $isSecretary ? collect() : Barangay::orderBy('name')->get(['id', 'name']);
        return view('reports.population-history', compact('submissions', 'isSecretary', 'barangays', 'areaId', 'filters'));
    }

    public function store(Request $request, PopulationSummary $summary, PopulationReportSubmissionSnapshot $snapshots)
    {
        $this->authorizeStaff($request);
        $user = $request->user();
        abort_unless($user->hasRole(User::ROLE_BARANGAY), 403);
        $request->validate(['preview_token' => ['required', 'uuid']]);
        $token = $request->string('preview_token')->toString();
        $existing = PopulationReportSubmission::where('barangay_id', $user->barangay_id)->where('submission_key', $token)->first();
        if ($existing) return redirect()->route('reports.population.submissions.show', $existing);
        $preview = $request->session()->get('population_submission_previews.'.$token);
        if (! $preview || (int) $preview['user_id'] !== (int) $user->id || (int) $preview['barangay_id'] !== (int) $user->barangay_id || $preview['expires_at'] < time()) {
            throw ValidationException::withMessages(['submission' => 'This preview expired. Reload the summary and review it before submitting.']);
        }
        $signatures = app(\App\Services\PopulationReportSignatures::class)->capture($request);
        $submission = DB::transaction(function () use ($preview, $summary, $snapshots, $user, $token, $signatures) {
            Barangay::whereKey($user->barangay_id)->lockForUpdate()->firstOrFail();
            $current = $summary->build($user->barangay_id, 'summary');
            $current['monitoring']['year'] = $preview['snapshot']['monitoring']['year'];
            $current['monitoring']['semester'] = $preview['snapshot']['monitoring']['semester'];
            $hash = $snapshots->hash($preview['snapshot'], true);
            if (! hash_equals($hash, $snapshots->hash($snapshots->capture($current), true))) {
                throw ValidationException::withMessages(['submission' => 'The report data changed after this preview. Reload and review the updated summary before submitting.']);
            }
            $signed = $preview['snapshot'];
            $signed['signatures'] = $signatures;
            $signed['monitoring']['preparedBy'] = $signatures['prepared']['name'];
            $signed['monitoring']['submittedBy'] = $signatures['submitted']['name'];
            $hash = $snapshots->hash($signed, true);
            // Parehas nga report dili i-duplicate, bisan double click o laing preview token.
            $same = PopulationReportSubmission::where(['barangay_id' => $user->barangay_id,
                'report_year' => $current['monitoring']['year'], 'semester' => $current['monitoring']['semester'], 'content_hash' => $hash])->first();
            if ($same) return $same;
            $saved = PopulationReportSubmission::create(['barangay_id' => $user->barangay_id, 'barangay_name' => $current['monitoring']['barangay'],
                'submitted_by' => $user->id, 'submitted_by_name' => $user->name,
                'report_year' => $current['monitoring']['year'], 'semester' => $current['monitoring']['semester'],
                'total_records' => $current['totalRecords'], 'submission_key' => $token,
                'snapshot' => $signed, 'snapshot_hash' => $snapshots->hash($signed),
                'content_hash' => $hash, 'submitted_at' => now()]);
            RegistryActivity::create(['barangay_id' => $user->barangay_id, 'user_id' => $user->id,
                'description' => 'Population summary '.$saved->reference.' submitted to Municipal DILG/Admin',
                'changes' => ['record_type' => 'population_report_submission', 'submission_id' => $saved->id,
                    'report_year' => $saved->report_year, 'semester' => $saved->semester]]);
            return $saved;
        });
        return redirect()->route('reports.population.submissions.show', $submission)
            ->with('status', 'Population summary submitted to Municipal DILG/Admin and saved in Submission History.');
    }

    public function signing(Request $request)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->hasRole(User::ROLE_BARANGAY), 403);
        $request->validate(['preview_token'=>['required','uuid']]);
        $token = $request->string('preview_token')->toString();
        $preview = $request->session()->get('population_submission_previews.'.$token);
        if (! $preview || (int) $preview['user_id'] !== (int) $request->user()->id
            || (int) $preview['barangay_id'] !== (int) $request->user()->barangay_id || $preview['expires_at']<time()) {
            return redirect()->route('reports.population')->withErrors(['submission'=>'Preview expired. Reload the summary and click Submit again.']);
        }
        return response()->view('reports.population-sign', ['previewToken'=>$token, 'report'=>$preview['snapshot'],
            'savedSignatures'=>app(\App\Services\PopulationReportSignatures::class)->savedFor($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, PopulationReportSubmission $submission, PopulationReportSubmissionSnapshot $snapshots, PopulationSpreadsheet $spreadsheet)
    {
        $this->authorizeStaff($request);
        if ($request->user()->hasRole(User::ROLE_BARANGAY)) abort_unless((int) $submission->barangay_id === (int) $request->user()->barangay_id, 403);
        $request->validate(['print' => ['nullable', 'boolean'], 'download' => ['nullable', 'boolean']]);
        if ($submission->is_historical_archive) {
            $archive = $submission->snapshot;
            abort_unless(hash_equals($submission->snapshot_hash, $snapshots->hash($archive)), 409, 'Saved report integrity check failed.');
            if ($request->routeIs('reports.population.submissions.source')) {
                $sha = $archive['source_sha256'];
                abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $sha), 409);
                $path = 'private/population-reports/'.$sha.'.jpg';
                abort_unless(Storage::disk('local')->exists($path), 404);
                abort_unless(hash_equals($sha, hash('sha256', Storage::disk('local')->get($path))), 409, 'Original photograph integrity check failed.');
                return response()->file(Storage::disk('local')->path($path), [
                    'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                    'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$submission->reference.'-original.jpg"',
                ]);
            }
            abort_unless($request->routeIs('reports.population.submissions.show'), 404);
            return response()->view('reports.population-archive', compact('submission', 'archive'))
                ->header('Cache-Control', 'private, no-store');
        }
        abort_if($request->routeIs('reports.population.submissions.source'), 404);
        $report = $snapshots->restore($submission);
        $selectedSection = 'summary'; $reportTitle = 'Population Summary'; $detailGroups = collect();
        if ($request->routeIs('reports.population.submissions.pdf')) {
            return Pdf::loadView('reports.population-pdf', compact('report', 'selectedSection', 'reportTitle', 'detailGroups'))
                ->setPaper('a4', 'portrait')->download($submission->reference.'-population-summary.pdf');
        }
        if ($request->routeIs('reports.population.submissions.excel')) {
            return response($spreadsheet->build($report, $selectedSection, $reportTitle), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$submission->reference.'-population-summary.xlsx"',
                'Cache-Control' => 'private, no-store']);
        }
        return response()->view('reports.population-submission', compact('report', 'submission', 'selectedSection', 'reportTitle', 'detailGroups')
            + ['printMode' => $request->boolean('print')])->header('Cache-Control', 'private, no-store');
    }
}
