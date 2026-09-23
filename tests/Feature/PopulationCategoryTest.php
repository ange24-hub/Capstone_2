<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, User};
use App\Services\PopulationSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopulationCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function member(Barangay $area, string $houseNumber, ?string $family, string $name, array $extra = []): Inhabitant
    {
        $house = Household::firstOrCreate(['barangay_id' => $area->id, 'household_number' => $houseNumber]);
        return Inhabitant::create(array_merge([
            'barangay_id' => $area->id, 'household_id' => $house->id, 'family_number' => $family,
            'first_name' => $name, 'last_name' => 'Example', 'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE,
        ], $extra));
    }

    public function test_family_and_household_lists_match_existing_counts_and_preserve_scope(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->member($area, '64', '64', 'FamilyHead', ['relationship_to_head' => 'Head']);
        $this->member($area, '64', '64', 'FamilyChild', ['relationship_to_head' => 'Child']);
        $this->member($area, '64.1', '64.1', 'DecimalFamily', ['relationship_to_head' => 'HEAD']);
        $this->member($area, '64.10', '64.10', 'AnotherFamily', ['relationship_to_head' => 'Self']);
        $this->member($area, '70', null, 'SinglePerson');
        $this->member($area, '80', null, 'UnassignedOne');
        $this->member($area, '80', null, 'UnassignedTwo');
        Household::create(['barangay_id' => $area->id, 'household_number' => '99']);
        $this->member($other, '64', '64', 'PrivateOtherArea');
        $families = $this->actingAs($staff)->get(route('reports.population', ['section' => 'families']))
            ->assertOk()->assertSee('FamilyHead Example')->assertDontSee('FamilyChild Example')->assertSee('DecimalFamily Example')
            ->assertSee('SinglePerson Example')->assertDontSee('UnassignedOne Example')->assertDontSee('PrivateOtherArea')
            ->assertDontSee('<h2>2. Population by sex', false)->viewData('report');
        $this->assertSame(4, $families['totalFamilies']);
        $this->assertCount(4, $families['detailGroups']);
        $this->assertSame(['Family 64', 'Family 64.1', 'Family 64.10'], $families['detailGroups']->take(3)->pluck('title')->all());
        $this->assertCount(2, $families['detailGroups']->first()['members']);
        $houses = $this->get(route('reports.population', ['section' => 'households']))->assertOk()
            ->assertDontSee('UnassignedOne Example')->assertSee('Household 99')->assertSee('Head not identified')
            ->assertSee('FamilyHead Example')->assertDontSee('FamilyChild Example')->assertDontSee('DecimalFamily Example')->assertDontSee('AnotherFamily Example')
            ->viewData('report');
        $this->assertCount($houses['totalHouseholds'], $houses['detailGroups']);
        $this->assertSame(4, $houses['totalHouseholds']);
        $this->assertCount(4, $houses['detailGroups']->first()['members']);
        foreach (['families' => $families, 'households' => $houses] as $section => $report) {
            $html = view('reports.population-pdf', ['report' => $report, 'selectedSection' => $section,
                'reportTitle' => PopulationSummary::SECTIONS[$section]])->render();
            $this->assertStringContainsString('FamilyHead Example', $html);
            $this->assertStringNotContainsString('FamilyChild Example', $html);
            $this->assertStringNotContainsString('UnassignedOne Example', $html);
            $this->assertStringNotContainsString('Relationship to head', $html);
            if ($section === 'households') {
                $this->assertStringNotContainsString('DecimalFamily Example', $html);
            }
            $this->get(route('reports.population.pdf', ['section' => $section]))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        $municipal = app(PopulationSummary::class)->build(null, 'families');
        $this->assertCount(5, $municipal['detailGroups']);
    }

    public function test_missing_or_ambiguous_heads_are_not_replaced_with_arbitrary_members(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $this->member($area, '1', '1', 'ChildOnly', ['relationship_to_head' => 'Child']);
        $this->member($area, '2', '2', 'FirstHead', ['relationship_to_head' => 'Head']);
        $this->member($area, '2', '2', 'SecondHead', ['relationship_to_head' => 'Head']);
        $this->member($area, '3', '3', 'RecordedFamilyHead', ['relationship_to_head' => 'Family head']);
        $this->member($area, '3', '3', 'RecordedHouseholdHead', ['relationship_to_head' => 'Household head']);
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (['families', 'households'] as $section) {
            $response = $this->actingAs($staff)->get(route('reports.population', ['section' => $section]))->assertOk()
                ->assertDontSee('ChildOnly Example')->assertDontSee('FirstHead Example')->assertDontSee('SecondHead Example')
                ->assertSee('Head not identified')->assertSee('Multiple heads recorded - needs verification');
            $response->assertSee(($section === 'families' ? 'RecordedFamilyHead' : 'RecordedHouseholdHead').' Example')
                ->assertDontSee(($section === 'families' ? 'RecordedHouseholdHead' : 'RecordedFamilyHead').' Example');
        }
    }

    public function test_senior_and_pwd_names_and_downloads_include_only_the_selected_category(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->member($area, '1', '1', 'AgeSenior', ['birth_date' => '1966-09-08']);
        $this->member($area, '1', '1', 'BothMarkers', ['birth_date' => '1960-01-01', 'remarks' => 'PWD; SC']);
        $this->member($area, '1', '1', 'RemarksSenior', ['remarks' => 'senior citizen']);
        $this->member($area, '1', '1', 'PwdOnly', ['remarks' => 'PWD', 'birth_date' => '2000-01-01']);
        $this->member($area, '1', '1', 'NotQualified', ['remarks' => 'Non-PWD; not SC', 'birth_date' => '1966-09-09']);
        $this->actingAs($staff);
        foreach (['seniors' => [3, 'AgeSenior', 'PwdOnly'], 'pwd' => [2, 'PwdOnly', 'AgeSenior']] as $section => [$count, $included, $excluded]) {
            $response = $this->get(route('reports.population', ['section' => $section]))->assertOk()
                ->assertSee($included.' Example')->assertDontSee($excluded.' Example')->assertDontSee('NotQualified Example');
            $report = $response->viewData('report');
            $this->assertCount($count, $report['detailGroups']->first()['members']);
            $html = view('reports.population-pdf', ['report' => $report, 'selectedSection' => $section,
                'reportTitle' => PopulationSummary::SECTIONS[$section]])->render();
            $this->assertStringContainsString($included.' Example', $html);
            $this->assertStringNotContainsString($excluded.' Example', $html);
            $this->assertStringNotContainsString('Population by sex', $html);
            $pdf = $this->get(route('reports.population.pdf', ['section' => $section]))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $this->assertStringContainsString('population-'.$section.'-barangay-'.$area->id, $pdf->headers->get('content-disposition'));
        }
    }

    public function test_selected_tables_and_permissions_apply_to_both_screen_and_download(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff);
        foreach (['sex', 'ages', 'coverage', 'area-ages'] as $section) {
            $response = $this->get(route('reports.population', ['section' => $section]))->assertOk();
            $html = view('reports.population-pdf', ['report' => $response->viewData('report'), 'selectedSection' => $section,
                'reportTitle' => PopulationSummary::SECTIONS[$section]])->render();
            $this->assertSame(1, substr_count($html, '<h2>'));
            $this->assertStringContainsString(PopulationSummary::SECTIONS[$section], $html);
        }
        foreach (['reports.population', 'reports.population.pdf'] as $route) {
            $this->get(route($route, ['section' => 'families', 'barangay_id' => $other->id]))->assertForbidden();
            $this->getJson(route($route, ['section' => 'invalid']))->assertUnprocessable();
            $this->getJson(route($route, ['section' => 'families', 'page' => -1]))->assertUnprocessable();
        }
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT, 'barangay_id' => $area->id]);
        foreach (['reports.population', 'reports.population.pdf'] as $route) {
            $this->actingAs($resident)->get(route($route, ['section' => 'families']))->assertForbidden();
        }
    }

    public function test_pdf_includes_all_selected_entries_even_when_the_screen_is_paginated(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (range(1, 51) as $index) {
            $this->member($area, (string) $index, (string) $index, 'Member'.str_pad($index, 2, '0', STR_PAD_LEFT));
        }
        $this->actingAs($staff)->get(route('reports.population', ['section' => 'residents']))->assertOk()
            ->assertSee('Member01 Example')->assertDontSee('Member51 Example');
        $this->get(route('reports.population', ['section' => 'residents', 'page' => 2]))->assertOk()->assertSee('Member51 Example');
        $this->get(route('reports.population', ['section' => 'families']))->assertOk()
            ->assertViewHas('detailGroups', fn ($groups) => $groups->count() === 15);
        // Inspect the exact data passed to the download renderer, independently of screen pagination.
        Pdf::shouldReceive('loadView')->once()->with('reports.population-pdf', \Mockery::on(function ($data) {
            $this->assertSame('residents', $data['selectedSection']);
            $this->assertCount(51, $data['detailGroups']->first()['members']);
            $this->assertStringContainsString('Member51 Example', view('reports.population-pdf', $data)->render());
            return true;
        }))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->with('a4')->andReturnSelf();
        Pdf::shouldReceive('download')->once()->andReturn(response('download'));
        $this->get(route('reports.population.pdf', ['section' => 'residents', 'page' => 2]))->assertOk();
    }
}
