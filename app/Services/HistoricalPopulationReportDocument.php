<?php

namespace App\Services;

use App\Models\{Barangay, PopulationReportSubmission};

class HistoricalPopulationReportDocument
{
    public function build(PopulationReportSubmission $submission): array
    {
        $archive = $submission->snapshot;
        abort_unless($submission->is_historical_archive && hash_equals($submission->snapshot_hash,
            app(PopulationReportSubmissionSnapshot::class)->hash($archive)), 409, 'Saved report integrity check failed.');
        $transcriptions = require resource_path('reports/carnaga-population-transcriptions.php');
        $source = $transcriptions[$archive['source_sha256']] ?? null;
        if ($source && ($source['year'] !== $submission->report_year || $source['semester'] !== $submission->semester)) $source = null;

        $report = ['historical'=>true, 'generatedAt'=>$submission->submitted_at,
            'scopeLabel'=>'Barangay '.$submission->barangay_name, 'totalRecords'=>$archive['population'],
            'totalHouseholds'=>$archive['households'], 'totalFamilies'=>$archive['families'],
            'ageTotalSum'=>$archive['age_total_sum'], 'coveredBarangays'=>1, 'totalBarangays'=>1,
            'sex'=>['Male'=>null,'Female'=>null,'Not specified / other'=>0], 'detailGroups'=>collect()];
        $barangay = new Barangay(['name'=>$submission->barangay_name]);
        $form = app(PopulationMonitoringReport::class)->build(collect(), $report, collect([$barangay]));
        $form['year']=$submission->report_year; $form['semester']=$submission->semester;
        $form['preparedBy']=$source['prepared_by'] ?? null;
        $form['submittedBy']=$source['submitted_by'] ?? null;
        foreach ($form['ages'] as $index=>&$row) {
            $row['male']=$source['male'][$index] ?? null;
            $row['female']=$source['female'][$index] ?? null;
            $row['total']=$archive['age_totals'][$index];
            $row['remarks']=$source && ($row['male']+$row['female'])!==$row['total'] ? 'Source sex counts differ from saved total' : '';
        }
        unset($row);
        if ($source) {
            $report['sex']['Male']=array_sum($source['male']);
            $report['sex']['Female']=array_sum($source['female']);
        }
        foreach ($form['sectors'] as $index=>&$row) {
            $values=$source['sectors'][$index] ?? null;
            $row['available']=$values!==null;
            $row['male']=$values[0] ?? null; $row['female']=$values[1] ?? null; $row['total']=$values[2] ?? null;
            $row['remarks']=$values ? '' : ($source ? 'Blank in original' : 'Not transcribed');
        }
        unset($row);
        $report['monitoring']=$form;
        // Retain the source's unknown date and signatures; do not apply today's
        // date, current staff names, or remembered current signatures to history.
        return $report;
    }
}
