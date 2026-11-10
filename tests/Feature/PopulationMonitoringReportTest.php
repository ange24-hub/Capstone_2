<?php

namespace Tests\Feature;

use App\Models\{Barangay,Household,Inhabitant,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PopulationMonitoringReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_c_five_year_bands_include_unknown_age_and_unspecified_sex_in_total(): void
    {
        $this->travelTo(Carbon::parse('2026-05-28 10:00:00'));
        $area=Barangay::where('name','Carnaga')->firstOrFail();
        $house=Household::create(['barangay_id'=>$area->id,'household_number'=>'FORM-C']);
        foreach([4,5,9,10,14,15,19,79,80,null] as $index=>$age) {
            Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$house->id,'first_name'=>'Fixture',
                'last_name'=>(string)$index,'sex'=>$age===null?'':'Male','family_number'=>'1',
                'birth_date'=>$age===null?null:now()->copy()->subYears($age)->toDateString()]);
        }
        $response=$this->actingAs(User::factory()->create(['role'=>'municipal_lgu']))
            ->get(route('reports.population',['barangay_id'=>$area->id,'report_year'=>2027,'semester'=>1]))->assertOk()
            ->assertSee('RBI FORM C (Revised 2024)')->assertSee('MONITORING REPORT')->assertSee('Population by Age Bracket:');
        $report=$response->viewData('report');$rows=collect($report['monitoring']['ages'])->keyBy('label');
        $this->assertSame(1,$rows['Under 5 years old']['total']);
        $this->assertSame(2,$rows['5-9 years old']['total']);
        $this->assertSame(2,$rows['10-14 years old']['total']);
        $this->assertSame(2,$rows['15-19 years old']['total']);
        $this->assertSame(1,$rows['75-79 years old']['total']);
        $this->assertSame(1,$rows['80 years old and over']['total']);
        $this->assertSame(1,$rows['Age not recorded / invalid']['unspecified']);
        $this->assertSame($report['totalRecords'],$rows->sum('total'));
        $this->assertSame(10,$report['totalRecords']);
    }

    public function test_sector_counts_use_explicit_positive_data_and_citizenship_is_not_inferred(): void
    {
        $area=Barangay::firstOrFail();$home=Household::create(['barangay_id'=>$area->id,'household_number'=>'SECTOR']);
        foreach([
            ['PWD OFW SOLO PARENT IP','Worker','Single','2000-01-01'],
            ['NO PWD; NOT OFW; NON SOLO PARENT; NO IP','None','Married','2000-01-01'],
            ['','Student',null,'2016-01-01'],
            ['OSC','',null,'2016-01-01'],
            ['OSY','',null,'2006-01-01'],
            ['OSC','',null,'2000-01-01'],
            ['','Unemployed',null,'2000-01-01'],
        ] as $i=>[$remarks,$occupation,$civil,$birth]) Inhabitant::create(['barangay_id'=>$area->id,
            'household_id'=>$home->id,'first_name'=>'Fixture','last_name'=>(string)$i,'sex'=>'Female',
            'birth_date'=>$birth,'remarks'=>$remarks,'occupation'=>$occupation,'civil_status'=>$civil,'birth_place'=>'Philippines']);
        $this->travelTo(Carbon::parse('2026-05-28'));
        $report=$this->actingAs(User::factory()->create(['role'=>'municipal_lgu']))->get(route('reports.population',['barangay_id'=>$area->id,'report_year'=>2027,'semester'=>1]))->assertOk()->viewData('report');
        $rows=collect($report['monitoring']['sectors'])->keyBy('label');
        foreach(['Person with Disabilities (PWDs)','Overseas Filipino Workers (OFWs)','Solo Parents','Indigenous Peoples (IPs)',
            'Unemployed','Out of School Children (OSC) (6-14 years old)','Out of School Youth (OSY) (15-24 years old)'] as $label) $this->assertSame(1,$rows[$label]['total'],$label);
        $this->assertFalse($rows['Labor Force']['available']);
        $this->assertFalse($rows['Citizenship: Filipino']['available']);
        $this->assertFalse($rows['Citizenship: Foreigner']['available']);
    }

    public function test_report_period_is_preserved_in_exports_without_reconstructing_historical_counts(): void
    {
        $this->travelTo(Carbon::parse('2026-05-28'));
        $area=Barangay::where('name','Carnaga')->firstOrFail();$area->update(['secretary_name'=>'Configured Secretary','punong_barangay_name'=>'Configured Captain']);
        $user=User::factory()->create(['role'=>'barangay','barangay_id'=>$area->id]);
        $parameters=['report_year'=>2027,'semester'=>2];
        $response=$this->actingAs($user)->get(route('reports.population',$parameters))->assertOk()
            ->assertSee('2nd Semester of CY 2027')->assertSee('Configured Secretary')->assertSee('Configured Captain')
            ->assertSee('historical population is not reconstructed');
        $this->assertSame(2027,$response->viewData('report')['monitoring']['year']);
        $this->assertSame(2026,$response->viewData('report')['generatedAt']->year);
        $this->get(route('reports.population.pdf',$parameters))->assertOk()->assertHeader('content-type','application/pdf');
        $xlsx=$this->get(route('reports.population.excel',$parameters))->assertOk()->getContent();
        $path=tempnam(sys_get_temp_dir(),'form-c-test-');file_put_contents($path,$xlsx);$zip=new \ZipArchive;$zip->open($path);
        try{$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$this->assertStringContainsString('2nd Semester of CY 2027',$sheet);
            $this->assertStringContainsString('Population by Age Bracket:',$sheet);$this->assertStringContainsString('orientation="portrait"',$sheet);
            $this->assertStringContainsString('Not recorded',$sheet);
        }finally{$zip->close();unlink($path);}
        $this->getJson(route('reports.population',['semester'=>3]))->assertUnprocessable();
    }
}
