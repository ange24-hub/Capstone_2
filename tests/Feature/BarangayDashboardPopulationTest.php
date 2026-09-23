<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayDashboardPopulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_are_scoped_and_allow_overlapping_categories(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 9, 17)->startOfDay());
        $area = Barangay::where('name', 'Maslog')->firstOrFail();
        $other = Barangay::where('name', 'Biasong')->firstOrFail();
        foreach ([$area, $other] as $barangay) {
            $house = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'TEST-1']);
            foreach ([
                ['PWD / SC / INDIGENT', '1960-01-01', 'active'],
                ['Non-PWD; not SC; Non-indigent', '2000-01-01', 'active'],
                ['Indigent: No; [Source: PWD-SC-INDIGENT.xlsx]', null, 'active'],
                [null, '1966-09-17', 'active'],
                ['PWD / SC / INDIGENT', null, 'inactive'],
            ] as [$remarks, $birth, $status]) {
                Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $house->id,
                    'first_name' => 'Test', 'last_name' => 'Resident', 'sex' => 'Female',
                    'remarks' => $remarks, 'birth_date' => $birth, 'status' => $status]);
            }
        }
        foreach ([[$area, ['residents' => 4, 'pwd' => 1, 'seniors' => 2, 'indigent' => 1]],
            [$other, ['residents' => 5, 'pwd' => 2, 'seniors' => 3, 'indigent' => 2]]] as [$barangay, $counts]) {
            $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
            $this->actingAs($secretary)->get(route('dashboard.barangay', ['barangay_id' => $other->id]))
                ->assertOk()->assertViewHas('populationCounts', $counts)
                ->assertSee('Resident sectors')->assertSee('Philippine Standard Time (UTC+8)');
        }
    }

    public function test_empty_barangay_dashboard_has_no_invalid_percentages(): void
    {
        $area = Barangay::where('name', 'Maslog')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($secretary)->get(route('dashboard.barangay'))->assertOk()
            ->assertViewHas('populationCounts', ['residents' => 0, 'pwd' => 0, 'seniors' => 0, 'indigent' => 0])
            ->assertSee('No resident records yet');
    }
}
