<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use App\Services\GisLayers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GisMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_a_named_household_and_see_its_marker_without_creating_residents(): void
    {
        $area = Barangay::where('name', 'San Isidro')->firstOrFail();
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($user)->post(route('spatial.households.store'), [
            'barangay_id' => $area->id, 'household_name' => 'Santos Family',
            'latitude' => '10.2688410', 'longitude' => '125.0266880',
        ])->assertRedirect(route('spatial.index', ['barangay_id' => $area->id]))
            ->assertSessionHas('mapped_household_id');
        $household = Household::where('household_name', 'Santos Family')->firstOrFail();
        $this->assertStringStartsWith('GIS-', $household->household_number);
        $this->assertEquals(10.2688410, $household->latitude);
        $this->assertEquals(125.0266880, $household->longitude);
        $this->assertDatabaseCount('inhabitants', 0);
        $this->get(route('spatial.index', ['barangay_id' => $area->id]))->assertOk()
            ->assertSee('Santos Family')->assertSee('Add household')
            ->assertViewHas('householdCount', 1)->assertViewHas('populationCount', 0)
            ->assertViewHas('markers', fn ($markers) => $markers->first()['household_name'] === 'Santos Family');
    }

    public function test_household_save_enforces_scope_valid_coordinates_and_unique_number(): void
    {
        $area = Barangay::where('name', 'San Isidro')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]));
        $point = $this->location($area);
        $payload = ['barangay_id' => $area->id, 'household_name' => 'Test Family', 'household_number' => 'HH-MAP-1',
            'latitude' => $point[1], 'longitude' => $point[0]];
        $this->postJson(route('spatial.households.store'), array_replace($payload, ['barangay_id' => $other->id]))->assertForbidden();
        $this->post(route('spatial.households.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('households', ['household_number' => 'HH-MAP-1', 'barangay_id' => $area->id]);
        $this->assertDatabaseMissing('households', ['barangay_id' => $other->id]);
        $this->postJson(route('spatial.households.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('household_number');
        $this->postJson(route('spatial.households.store'), array_replace($payload, [
            'household_number' => 'HH-MAP-2', 'household_name' => ' ', 'latitude' => 91, 'longitude' => -181,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['household_name', 'latitude', 'longitude']);
        $this->postJson(route('spatial.households.store'), ['household_name' => 'Missing location'])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->assertDatabaseCount('households', 1);
    }

    public function test_household_creation_requires_approved_staff_and_assignment(): void
    {
        $this->post(route('spatial.households.store'), [])->assertRedirect(route('login'));
        foreach ([['role' => User::ROLE_RESIDENT], ['role' => User::ROLE_BARANGAY, 'barangay_id' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))->post(route('spatial.households.store'), [])->assertForbidden();
        }
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::firstOrFail()->id,
            'approval_status' => User::APPROVAL_PENDING]);
        $this->actingAs($user)->post(route('spatial.households.store'), [])->assertRedirect(route('approval.pending'));
        $this->assertDatabaseCount('households', 0);
    }

    public function test_municipal_map_loads_the_imported_layers_without_merging_buildings_into_registry_totals(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($user)->get(route('spatial.index'))->assertOk()
            ->assertSee('GIS Mapping')->assertSee('vendor/leaflet/leaflet.js')
            ->assertSee('gis-map-config')->assertViewHas('householdCount', 0);
        $this->getJson(route('spatial.layers', 'barangay-boundaries'))->assertOk()->assertJsonCount(29, 'features');
        $points = $this->getJson(route('spatial.layers', 'building-points'))
            ->assertOk()->assertJsonCount(4001, 'features')->assertHeader('Content-Type', 'application/geo+json');
        $this->assertStringContainsString('no-store', $points->headers->get('Cache-Control'));
        $this->assertSame(['barangay', 'barangay_code'], array_keys($points->json('features.0.properties')));
        foreach (['municipal-boundary', 'flood', 'landslide', 'storm-surge'] as $layer) {
            $response = $this->get(route('spatial.layers', $layer))->assertOk()
                ->assertHeader('Content-Type', 'application/geo+json');
            $this->assertSame(resource_path('gis/'.GisLayers::FILES[$layer]), $response->baseResponse->getFile()->getPathname());
        }
        $this->getJson(route('spatial.layers', 'unknown'))->assertNotFound();
    }

    public function test_secretary_sees_whole_municipality_but_only_own_households_are_editable(): void
    {
        $area = Barangay::where('name', 'San Isidro')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach ([$area, $other] as $barangay) {
            $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => 'GIS-'.$barangay->id,
                'latitude' => 10.26, 'longitude' => 125.02]);
            Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $household->id,
                'first_name' => 'Map', 'last_name' => 'Resident', 'sex' => 'Female']);
        }
        $this->actingAs($user)->get(route('spatial.index', ['barangay_id' => $other->id]))->assertOk()
            ->assertViewHas('selectedBarangayId', null)->assertViewHas('householdCount', 2)
            ->assertViewHas('populationCount', 2)
            ->assertViewHas('markers', fn ($markers) => $markers->where('barangay', 'San Isidro')->first()['edit_url'] !== null
                && $markers->where('barangay', 'Canlupao')->first()['edit_url'] === null);
        $response = $this->getJson(route('spatial.layers', ['layer' => 'building-points', 'barangay_id' => $other->id]))->assertOk();
        $response->assertJsonCount(4001, 'features');
        $this->getJson(route('spatial.layers', 'barangay-boundaries'))->assertOk()->assertJsonCount(29, 'features');
    }

    public function test_gis_names_match_registry_aliases_and_municipal_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]));
        foreach (['Banday', 'Bogo', 'Punong', 'Higosoan', 'San Antonio', 'San Agustin'] as $name) {
            $area = Barangay::where('name', $name)->firstOrFail();
            $boundaries = $this->getJson(route('spatial.layers', ['layer' => 'barangay-boundaries', 'barangay_id' => $area->id]))
                ->assertOk()->assertJsonCount(1, 'features');
            $code = (string) $boundaries->json('features.0.properties.BARANGAY_C');
            $points = $this->getJson(route('spatial.layers', ['layer' => 'building-points', 'barangay_id' => $area->id]))->assertOk();
            $this->assertNotEmpty($points->json('features'));
            $this->assertSame([$code], array_values(array_unique(array_column(array_column($points->json('features'), 'properties'), 'barangay_code'))));
        }
        $this->getJson(route('spatial.index', ['barangay_id' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('spatial.layers', ['layer' => 'building-points', 'barangay_id' => 999999]))->assertUnprocessable();
    }

    private function location(Barangay $area): array
    {
        $layers = app(GisLayers::class);
        foreach ($layers->read('building-points', $area)['features'] as $feature) {
            $point = $feature['geometry']['coordinates'];
            if ($layers->contains($area, $point[1], $point[0])) {
                return $point;
            }
        }
        $this->fail('No interior source point for '.$area->name);
    }

    public function test_secretary_can_edit_own_household_but_cannot_edit_or_move_into_another_barangay(): void
    {
        $area = Barangay::where('name', 'San Isidro')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $ownPoint = $this->location($area);
        $otherPoint = $this->location($other);
        $own = Household::create(['barangay_id' => $area->id, 'household_number' => 'MAP-OWN', 'household_name' => 'Original',
            'latitude' => $ownPoint[1], 'longitude' => $ownPoint[0]]);
        $foreign = Household::create(['barangay_id' => $other->id, 'household_number' => 'MAP-OTHER']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]));
        $payload = ['barangay_id' => $area->id, 'household_number' => 'MAP-OWN', 'household_name' => 'Updated Family',
            'latitude' => $ownPoint[1], 'longitude' => $ownPoint[0]];
        $this->get(route('spatial.index', ['edit_household' => $own->id]))->assertOk()->assertSee('Edit household')->assertSee('Original');
        $this->put(route('spatial.households.update', $own), $payload)->assertRedirect();
        $this->assertSame('Updated Family', $own->fresh()->household_name);
        $this->get(route('spatial.index', ['edit_household' => $foreign->id]))->assertForbidden();
        $this->putJson(route('spatial.households.update', $foreign), $payload)->assertForbidden();
        $this->putJson(route('spatial.households.update', $own), array_replace($payload, ['barangay_id' => $other->id]))->assertForbidden();
        $outside = array_replace($payload, ['latitude' => $otherPoint[1], 'longitude' => $otherPoint[0]]);
        $this->putJson(route('spatial.households.update', $own), $outside)->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->postJson(route('spatial.households.store'), array_replace($outside, ['household_number' => 'NEW-OUTSIDE']))->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->assertDatabaseCount('households', 2);
        $this->assertEqualsWithDelta($ownPoint[1], (float) $own->fresh()->latitude, 0.0000001);
    }

    public function test_map_and_layers_require_staff_access_and_an_assigned_secretary(): void
    {
        $urls = [route('spatial.index'), route('spatial.layers', 'building-points')];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach ([['role' => User::ROLE_RESIDENT], ['role' => User::ROLE_BARANGAY, 'barangay_id' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }
}
