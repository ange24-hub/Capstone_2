<?php

namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, ForecastLog, Household, Inhabitant, MigrationLog, MigrationRecord, PopulationReportSubmission, User};
use App\Services\{MigrationPredictionService, MigrationSummary, RecordedMigrationHistory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordedMigrationTrendsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\SubmittedArrivalFixture;

    private function person(Barangay $area, string $number = 'DATED-1'): Inhabitant
    {
        $house = Household::firstOrCreate(['barangay_id'=>$area->id,'household_number'=>$number]);
        return Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$house->id,
            'first_name'=>'Actual','last_name'=>'Movement','sex'=>'Female','status'=>'active']);
    }

    private function move(Inhabitant $person, string $date, string $type = 'in', ?string $reason = null): MigrationRecord
    {
        return MigrationRecord::create(['inhabitant_id'=>$person->id,'barangay_id'=>$person->barangay_id,
            'movement_date'=>$date,'type'=>$type,'reason'=>$reason]);
    }

    public function test_dashboard_report_and_pdf_use_actual_dates_and_count_duplicates_once_without_changing_reports(): void
    {
        $this->travelTo(Carbon::parse('2027-01-10 04:00:00','UTC'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $other = Barangay::where('name','Looc')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $person = $this->person($area);
        $this->move($person,'2026-01-12');
        $this->move($person,'2026-01-12'); // Retained duplicate, not a second move.
        $this->move($person,'2026-03-09'); // Same person on a different day is a separate event.
        $this->move($person,'2026-02-04','out');
        foreach (['2026-06-02','2026-01-12'] as $date) MigrationLog::create(['resident_id'=>$person->id,
            'barangay_id'=>$area->id,'migration_type'=>'in','transaction_date'=>$date]);
        $this->move($this->person($other),'2026-01-12');
        $this->submittedArrivals($area,$staff,'2026-04',[5,0,0]); // Reporting/save dates are not movement evidence.
        $tables = ['migration_records','migration_logs','barangay_rbi_updates','population_report_submissions','forecast_logs'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table=>\DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();

        $response = $this->actingAs($staff)->get(route('migration.dashboard',['year'=>2026,'barangay_id'=>$other->id]))->assertOk();
        $this->assertSame(3,$response->viewData('totalIn'));
        $this->assertSame(1,$response->viewData('totalOut'));
        $this->assertSame(2,$response->viewData('duplicateEvents'));
        $months = $response->viewData('monthlyTrend');
        $this->assertSame([1,1,1],[$months[0]['in'],$months[2]['in'],$months[5]['in']]);
        $this->assertSame(0,$months[3]['in']);
        $this->assertFalse($months[3]['in_known']);
        $response->assertSee('Actual movement months')->assertSee('No confirmed data')->assertDontSee('first-save month')
            ->assertDontSee('data-movement-month="2026-04"',false);
        $this->assertTrue($months[0]['in_known']);
        $this->get(route('migration.dashboard',['year'=>2026,'section'=>'events']))->assertOk()
            ->assertSee('Recent movement records')->assertViewHas('records',fn ($records) => $records->count() === 4);
        $report = $this->get(route('reports.migration',['year'=>2026]))->assertOk()->viewData('report');
        $this->assertSame([3,1,2,4],[$report['in'],$report['out'],$report['net'],$report['total']]);
        $this->assertSame($months->toArray(),$report['months']->toArray());
        $html = view('reports.migration-pdf',compact('report'))->render();
        $this->assertStringContainsString('No confirmed data',$html);
        $this->assertStringNotContainsString('first-save',$html);
        $this->get(route('reports.migration.pdf',['year'=>2026]))->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertSame($before,$snapshot());
    }

    public function test_only_confirmed_completed_zero_months_show_zero_and_source_changes_reopen_coverage(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 04:00:00','UTC'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $native = app(MigrationPredictionService::class);
        foreach (['in','out'] as $type) {
            $state = $native->monthSnapshot($area->id,$type,'2026-10');
            $native->confirmActualMonth($area->id,$type,'2026-10',$staff->id,$state['source_hash']);
        }
        $history = app(RecordedMigrationHistory::class);
        $months = $history->build($area->id,2026)['months'];
        $this->assertSame(0,$months[9]['in']);
        $this->assertTrue($months[9]['in_known']);
        $this->assertTrue($months[9]['out_known']);
        $this->assertSame('Confirmed complete',$months[9]['status']);
        $this->assertFalse($months[8]['in_known']);
        $this->assertFalse($months[10]['in_known']);
        $this->assertSame('Future month',$months[11]['status']);
        $before = ForecastLog::orderBy('id')->get()->map->getAttributes()->all();
        $this->move($this->person($area),'2026-10-03');
        $changed = $history->build($area->id,2026)['months'][9];
        $this->assertSame(1,$changed['in']);
        $this->assertFalse($changed['in_complete']);
        $this->assertTrue($changed['out_complete']);
        $this->assertSame('Recorded events; coverage unconfirmed',$changed['status']);
        $this->assertSame($before,ForecastLog::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_future_demo_and_cross_barangay_records_do_not_inflate_counts_and_default_year_is_philippine(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 17:00:00','UTC'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        $other = Barangay::where('name','Looc')->firstOrFail();
        $person = $this->person($area);
        $this->move($person,'2027-01-01');
        $this->move($person,'2027-01-02');
        $this->move($person,'2027-12-01','out');
        $this->move($person,'2027-01-01','out',\App\Services\MigrationForecastHistory::DEMO_MARKER);
        $this->move($this->person($area,'DEMO-MIGRATION-V1'),'2027-01-01');
        $foreign = $this->person($other);
        MigrationRecord::create(['inhabitant_id'=>$foreign->id,'barangay_id'=>$area->id,'type'=>'in','movement_date'=>'2027-01-01']);
        $this->move($foreign,'2027-01-01');
        $staff = User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $before = MigrationRecord::orderBy('id')->get()->map->getAttributes()->all();
        $response = $this->actingAs($staff)->get(route('migration.dashboard'))->assertOk();
        $this->assertSame(2027,$response->viewData('selectedYear'));
        $this->assertSame(1,$response->viewData('totalIn'));
        $this->assertSame(0,$response->viewData('totalOut'));
        $this->assertFalse($response->viewData('outKnown'));
        $this->assertSame($before,MigrationRecord::orderBy('id')->get()->map->getAttributes()->all());
        $this->get(route('reports.migration'))->assertOk()->assertViewHas('selectedYear',2027);
        $admin = User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('migration.dashboard'))->assertOk()->assertViewHas('totalIn',2);
    }

    public function test_missing_ledger_storage_does_not_turn_unavailable_history_into_verified_zero(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 04:00:00','UTC'));
        $area = Barangay::where('name','Hugpa')->firstOrFail();
        ForecastLog::create(['barangay_id'=>$area->id,'migration_type'=>'in','log_month'=>'2026-10',
            'actual_value'=>0,'actual_verified_at'=>now(),'actual_source_hash'=>hash('sha256','[]')]);
        \Schema::drop('migration_logs'); // Isolated SQLite fixture only.
        $month = app(RecordedMigrationHistory::class)->build($area->id,2026)['months'][9];
        $this->assertFalse($month['in_complete']);
        $this->assertFalse($month['in_known']);
    }
}
