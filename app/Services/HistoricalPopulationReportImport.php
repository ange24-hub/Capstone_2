<?php

namespace App\Services;

use App\Models\{Barangay, PopulationReportSubmission};
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;

class HistoricalPopulationReportImport
{
    public function import(Barangay $barangay, string $photo, array $data): PopulationReportSubmission
    {
        // Gikan sa dokumento ang period ug counts; walay giimbentong monthly dates.
        Validator::make($data, [
            'year' => 'required|integer|between:1900,'.now()->year,
            'semester' => 'required|integer|in:1,2',
            'population' => 'required|integer|min:0', 'households' => 'required|integer|min:0',
            'families' => 'required|integer|min:0', 'age_totals' => 'required|array|size:17',
            'age_totals.*' => 'required|integer|min:0',
        ])->validate();
        if (! is_file($photo) || (getimagesize($photo)['mime'] ?? '') !== 'image/jpeg') {
            throw new \InvalidArgumentException('A readable original JPEG photograph is required.');
        }
        $bytes = file_get_contents($photo);
        $sha = hash('sha256', $bytes);
        $snapshot = ['kind' => 'historical_population_photo', 'source_sha256' => $sha,
            'year' => (int) $data['year'], 'semester' => (int) $data['semester'],
            'population' => (int) $data['population'], 'households' => (int) $data['households'],
            'families' => (int) $data['families'], 'age_totals' => array_values($data['age_totals']),
            'age_total_sum' => array_sum($data['age_totals']), 'original_submission_date' => null,
            'date_note' => 'Exact original submission/accomplishment date not verified. Photo timestamp is not the reporting date.',
        ];
        $snapshots = app(PopulationReportSubmissionSnapshot::class);
        return DB::transaction(function () use ($barangay, $snapshot, $snapshots, $bytes, $sha) {
            Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            $existing = PopulationReportSubmission::where('barangay_id', $barangay->id)
                ->where('snapshot->source_sha256', $sha)->first();
            if ($existing) return $existing;
            // Private storage lamang. Ang authenticated source route ra ang makabasa niini.
            if (! Storage::disk('local')->put('private/population-reports/'.$sha.'.jpg', $bytes)) {
                throw new \RuntimeException('Could not preserve the original photograph.');
            }
            return PopulationReportSubmission::create([
                'barangay_id' => $barangay->id, 'barangay_name' => $barangay->name,
                'submitted_by' => null, 'submitted_by_name' => 'Historical paper report',
                'report_year' => $snapshot['year'], 'semester' => $snapshot['semester'],
                'total_records' => $snapshot['population'], 'submission_key' => (string) Str::uuid(),
                'snapshot' => $snapshot, 'snapshot_hash' => $snapshots->hash($snapshot),
                'content_hash' => $snapshots->hash($snapshot),
                // Sa archive entries, kini ang oras sa pag-archive, dili daang submission date.
                'submitted_at' => now(),
            ]);
        });
    }
}
