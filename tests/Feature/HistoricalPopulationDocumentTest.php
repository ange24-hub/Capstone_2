<?php

namespace Tests\Feature;

use App\Models\PopulationReportSubmission;
use App\Services\{HistoricalPopulationReportDocument, PopulationReportSubmissionSnapshot};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HistoricalPopulationDocumentTest extends TestCase
{
    private function submission(string $sha,array $source,int $population): PopulationReportSubmission
    {
        $totals=array_map(fn($male,$female)=>$male+$female,$source['male'],$source['female']);
        $snapshot=['kind'=>'historical_population_photo','source_sha256'=>$sha,'year'=>$source['year'],
            'semester'=>$source['semester'],'population'=>$population,'households'=>107,'families'=>120,
            'age_totals'=>$totals,'age_total_sum'=>array_sum($totals),'original_submission_date'=>null];
        return (new PopulationReportSubmission)->forceFill(['barangay_name'=>'Carnaga','report_year'=>$source['year'],
            'semester'=>$source['semester'],'snapshot'=>$snapshot,
            'snapshot_hash'=>app(PopulationReportSubmissionSnapshot::class)->hash($snapshot),'submitted_at'=>'2026-11-08 10:00:00']);
    }

    public function test_all_five_source_transcriptions_preserve_counts_period_and_unknown_original_signatures(): void
    {
        $sources=require resource_path('reports/carnaga-population-transcriptions.php');
        $populations=[417,459,456,458,466];
        foreach(array_values(array_keys($sources)) as $index=>$sha){
            $source=$sources[$sha];$saved=$this->submission($sha,$source,$populations[$index]);$original=$saved->snapshot;
            $report=app(HistoricalPopulationReportDocument::class)->build($saved);
            $this->assertSame($populations[$index],$report['totalRecords']);
            $this->assertSame($original['age_totals'],array_column($report['monitoring']['ages'],'total'));
            $this->assertSame($source['male'],array_column($report['monitoring']['ages'],'male'));
            $this->assertSame($source['female'],array_column($report['monitoring']['ages'],'female'));
            $this->assertSame($source['year'],$report['monitoring']['year']);
            $this->assertSame($source['semester'],$report['monitoring']['semester']);
            $this->assertSame($source['prepared_by'],$report['monitoring']['preparedBy']);
            $this->assertSame($source['submitted_by'],$report['monitoring']['submittedBy']);
            $this->assertArrayNotHasKey('signatures',$report);
            $this->assertFalse($report['monitoring']['sectors'][7]['available']);
            $this->assertNull($report['monitoring']['sectors'][7]['total']);
            $this->assertSame($original,$saved->snapshot);
            $html=view('reports.population-pdf',['report'=>$report,'selectedSection'=>'summary'])->render();
            $this->assertStringContainsString('HISTORICAL TRANSCRIPTION',$html);
            $this->assertStringContainsString('Not recorded on original',$html);
            $this->assertStringNotContainsString('Source: active registered residents',$html);
        }
    }

    public function test_recorded_zero_is_preserved_and_discrepancies_are_not_silently_corrected(): void
    {
        $sources=require resource_path('reports/carnaga-population-transcriptions.php');
        $sha='be2717081471daeece07a9ff705a066630b76cdf40d696e5e7f505cf2148c558';
        $saved=$this->submission($sha,$sources[$sha],466);
        $report=app(HistoricalPopulationReportDocument::class)->build($saved);
        $this->assertSame(466,$report['totalRecords']);$this->assertSame(447,$report['ageTotalSum']);
        $this->assertTrue($report['monitoring']['sectors'][2]['available']);
        $this->assertSame(0,$report['monitoring']['sectors'][2]['male']);
        $this->assertSame(0,$report['monitoring']['sectors'][2]['female']);
        $this->assertSame(0,$report['monitoring']['sectors'][2]['total']);
        $html=view('reports.population-pdf',['report'=>$report,'selectedSection'=>'summary'])->render();
        $this->assertStringContainsString('Needs reconciliation: reported population 466; age-bracket sum 447',$html);
    }

    public function test_unknown_source_never_invents_sex_sector_or_signature_values(): void
    {
        $sources=require resource_path('reports/carnaga-population-transcriptions.php');
        $saved=$this->submission(str_repeat('a',64),reset($sources),417);
        $report=app(HistoricalPopulationReportDocument::class)->build($saved);
        $this->assertNull($report['sex']['Male']);
        foreach($report['monitoring']['ages'] as $row){$this->assertNull($row['male']);$this->assertNull($row['female']);}
        foreach($report['monitoring']['sectors'] as $row){$this->assertFalse($row['available']);$this->assertNull($row['total']);}
        $this->assertArrayNotHasKey('signatures',$report);
    }

    public function test_tampered_saved_snapshot_cannot_be_digitized(): void
    {
        $sources=require resource_path('reports/carnaga-population-transcriptions.php');
        $saved=$this->submission(array_key_first($sources),reset($sources),417);
        $saved->snapshot_hash=str_repeat('0',64);
        $this->expectException(HttpException::class);
        app(HistoricalPopulationReportDocument::class)->build($saved);
    }
}
