<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopulationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_person_households_count_as_families_even_without_family_numbers(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        foreach ([$area, $other] as $barangay) {
            $cases = $barangay->id === $area->id
                ? ['1' => [null], '2' => ['2'], '3' => [null, ' '], '64' => [null], '64.1' => ['64.1'], '99' => []]
                : ['1' => [null]];
            foreach ($cases as $number => $families) {
                $house = Household::create(['barangay_id' => $barangay->id, 'household_number' => (string) $number]);
                foreach ($families as $family) {
                    Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
                        'first_name' => 'Example', 'last_name' => 'Resident', 'sex' => 'Male',
                        'family_number' => $family, 'status' => Inhabitant::STATUS_ACTIVE]);
                }
            }
        }
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $report = $this->actingAs($staff)->get(route('reports.population', ['barangay_id' => $area->id]))
            ->assertOk()->assertSee('A household with one recorded resident also counts as one family')->viewData('report');
        $this->assertSame(3, $report['totalFamilies']);
        $this->assertSame(1, $report['singlePersonFamiliesAdded']);
        $this->assertSame(3, $report['coverage'][0]['families']);
        $this->assertSame(4, $report['recordsWithoutFamily']);
        $this->assertSame(5, $report['totalHouseholds']);
        $all = $this->get(route('reports.population'))->assertOk()->viewData('report');
        $this->assertSame(4, $all['totalFamilies']);
        $this->assertSame(2, $all['singlePersonFamiliesAdded']);
        $this->assertStringContainsString('A household with one recorded resident also counts as one family', view('reports.population-pdf', compact('report'))->render());
    }

    public function test_pwd_and_seniors_use_remarks_and_birth_dates_without_double_counting(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        $cases = [
            ['PWD / SC', '1966-09-08'], // both sources, counted once
            ['senior citizen', null], // remarks only
            [null, '1960-01-01'], // age only
            ['pwd', '2000-01-01'],
            ['Non-PWD; not SC', '2000-01-01'],
            ['[Source: SC-PWD.xlsx]', null], // import metadata is not a marker
            ['school', null], // SC inside a word
            ['PWD: No; SC: No', null],
            ['Possible PWD; SC?', null],
            ['P.W.D.; S.C.', null],
            [null, '1966-09-09'], // 60th birthday tomorrow
            ['Person with disability', '2027-01-01'], // invalid age still allows PWD
        ];
        foreach ($cases as $index => [$remarks, $date]) {
            Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id,
                'first_name' => 'Example', 'last_name' => (string) $index, 'family_number' => '1',
                'sex' => $index % 2 ? 'Female' : 'Male', 'remarks' => $remarks, 'birth_date' => $date,
                'status' => Inhabitant::STATUS_ACTIVE]);
        }
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $response = $this->actingAs($staff)->get(route('reports.population', ['barangay_id' => $area->id]))
            ->assertOk()->assertSee('Persons with disability')->assertSee('Senior citizens');
        $report = $response->viewData('report');
        $this->assertSame(4, $report['totalPwd']);
        $this->assertSame(4, $report['totalSeniors']);
        $this->assertSame(2, $report['ages']['60+']);
        $this->assertSame(2, $report['seniorRemarksOnly']);
        $this->assertSame(4, $report['coverage'][0]['pwd']);
        $this->assertSame(4, $report['coverage'][0]['seniors']);
        $this->assertSame([6, 6, 0], array_values($report['coverage'][0]['sex']));
        $this->assertSame($report['ages'], $report['coverage'][0]['ages']);
        $empty = $this->get(route('reports.population', ['barangay_id' => $other->id]))->assertOk()->viewData('report');
        $this->assertSame(0, $empty['totalPwd']);
        $this->assertSame(0, $empty['totalSeniors']);
        $html = view('reports.population-pdf', compact('report'))->render();
        $this->assertStringContainsString('POPULATION SUMMARY REPORT', $html);
        $this->assertStringContainsString('Persons with disability', $html);
        $this->assertStringContainsString('Population by age', $html);
    }

    public function test_decimal_family_suffixes_share_a_household_without_merging_families(): void
    {
        $area = Barangay::where('name', 'Higosoan')->firstOrFail();
        $other = Barangay::where('name', 'Looc')->firstOrFail();
        foreach ([$area, $other] as $barangay) {
            foreach (['64', '64.1', '64.2', '64.10'] as $number) {
                $house = Household::create(['barangay_id' => $barangay->id, 'household_number' => $number]);
                foreach (range(1, 2) as $member) {
                    Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
                        'first_name' => 'Member '.$member, 'last_name' => 'Example', 'sex' => 'Male',
                        'family_number' => $number, 'status' => Inhabitant::STATUS_ACTIVE]);
                }
            }
        }
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $report = $this->actingAs($staff)->get(route('reports.population'))->assertOk()->viewData('report');
        $this->assertSame(2, $report['totalHouseholds']);
        $this->assertSame(8, $report['encodedHouseholdRows']);
        $this->assertSame(8, $report['totalFamilies']);
        $this->assertSame(16, $report['totalRecords']);
        $filtered = $this->get(route('reports.population', ['barangay_id' => $area->id]))->assertOk()->viewData('report');
        $this->assertSame(1, $filtered['totalHouseholds']);
        $this->assertSame(4, $filtered['totalFamilies']);
        $this->assertSame(1, $filtered['coverage'][0]['households']);
        $this->assertSame(4, $filtered['coverage'][0]['families']);
        $this->assertDatabaseCount('households', 8);
    }

    public function test_families_are_counted_once_per_barangay_and_missing_numbers_are_reported(): void
    {
        $areas = Barangay::whereIn('name', ['Looc', 'Canlupao'])->orderBy('name')->get();
        foreach ($areas as $index => $area) {
            $house = Household::create(['barangay_id' => $area->id, 'household_number' => 'FAMILY-HH']);
            $numbers = $index === 0 ? ['1', '1', '2', null, ' '] : ['1', '1'];
            foreach ($numbers as $position => $number) {
                Inhabitant::create([
                    'barangay_id' => $area->id, 'household_id' => $house->id,
                    'first_name' => 'Family', 'last_name' => 'Member '.$position, 'sex' => 'Male',
                    'family_number' => $number, 'status' => Inhabitant::STATUS_ACTIVE,
                ]);
            }
        }
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $response = $this->actingAs($staff)->get(route('reports.population'))->assertOk()
            ->assertSee('Population, families and households');
        $report = $response->viewData('report');
        $this->assertSame(3, $report['totalFamilies']);
        $this->assertSame(2, $report['totalHouseholds']);
        $this->assertSame(2, $report['recordsWithoutFamily']);
        $filtered = $this->get(route('reports.population', ['barangay_id' => $areas[0]->id]))->assertOk()->viewData('report');
        $this->assertSame(2, $filtered['totalFamilies']);
        $this->assertSame(2, $filtered['coverage'][0]['families']);
        $this->assertSame(2, $filtered['coverage'][0]['recordsWithoutFamily']);
    }

    public function test_report_counts_unknowns_age_boundaries_and_coverage(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $house = Household::create(['barangay_id'=>$area->id,'household_number'=>'POP-1']);
        foreach (['2021-09-09', '2021-09-08', '2008-09-08', '1966-09-08', null, '2027-01-01'] as $i=>$date) {
            Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$house->id,'first_name'=>'Report','last_name'=>'Resident '.$i,
                'birth_date'=>$date,'recorded_age'=>25,'sex'=>$i === 0 ? 'Male' : ($i === 1 ? 'Female' : ''),
                'status'=>$i === 0 ? Inhabitant::STATUS_MIGRATED_OUT : Inhabitant::STATUS_ACTIVE,
                'residence_status'=>Inhabitant::RESIDENCE_HERE]);
        }
        $staff=User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]);
        $response=$this->actingAs($staff)->get(route('reports.population',['barangay_id'=>$area->id]));
        $response->assertOk()->assertSee('Population Summary')->assertSee('Download PDF');
        $r=$response->viewData('report');
        $this->assertSame(6,$r['totalRecords']);
        $this->assertSame(1,$r['totalHouseholds']);
        $this->assertSame(0,$r['totalFamilies']);
        $this->assertSame(6,$r['recordsWithoutFamily']);
        $this->assertSame([1,1,1,1,2],array_values($r['ages']));
        $this->assertSame([1,1,4],array_values($r['sex']));
        $this->assertSame(5,$r['confirmedLivingHere']);
        $this->assertSame(1,$r['coveredBarangays']);
        $this->assertSame(1,$r['totalBarangays']);
        $this->get(route('reports.population',['barangay_id'=>$other->id]))->assertOk()
            ->assertSee('No resident records encoded in this scope')->assertSee('Not yet encoded');
        $all=$this->get(route('reports.population'))->assertOk()->viewData('report');
        $this->assertSame(1,$all['coveredBarangays']);
        $this->assertSame(Barangay::count(),$all['totalBarangays']);
        $this->getJson(route('reports.population',['barangay_id'=>999999]))->assertUnprocessable();
    }

    public function test_secretary_scope_is_enforced_for_screen_and_pdf(): void
    {
        $area=Barangay::where('name','Looc')->firstOrFail();
        $other=Barangay::where('name','Canlupao')->firstOrFail();
        $staff=User::factory()->create(['role'=>User::ROLE_BARANGAY,'barangay_id'=>$area->id]);
        $this->actingAs($staff)->get(route('reports.population'))->assertOk()->assertSee('Barangay Looc')->assertDontSee('Canlupao');
        foreach (['reports.population','reports.population.pdf'] as $route) {
            $this->get(route($route,['barangay_id'=>$other->id]))->assertForbidden();
        }
        $pdf=$this->get(route('reports.population.pdf'))->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('population-summary-barangay-'.$area->id, $pdf->headers->get('content-disposition'));
        $staff->barangay_id=null;
        $staff->save();
        $this->get(route('reports.population'))->assertForbidden();
    }

    public function test_guests_and_residents_cannot_access_population_reports(): void
    {
        foreach (['reports.population','reports.population.pdf'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $resident=User::factory()->create(['role'=>User::ROLE_RESIDENT]);
        foreach (['reports.population','reports.population.pdf'] as $route) {
            $this->actingAs($resident)->get(route($route))->assertForbidden();
        }
    }
}
