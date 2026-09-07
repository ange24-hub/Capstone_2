<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_barangay_has_the_same_reporting_navigation_and_dashboard_links(): void
    {
        foreach (Barangay::all() as $barangay) {
            $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
            $response = $this->actingAs($staff)->get(route('dashboard.barangay'))->assertOk();
            $html = $response->getContent();
            preg_match('/<nav class="side-nav".*?<\/nav>/s', $html, $match);
            $this->assertNotEmpty($match, $barangay->name);
            foreach (['RBI Forms' => 'barangay.rbi-updates.index', 'Deceased Records' => 'barangay.registry.deceased'] as $label => $route) {
                $this->assertStringContainsString('<span>'.$label.'</span>', $match[0], $barangay->name);
                $this->assertStringContainsString('href="'.route($route).'"', $match[0], $barangay->name);
            }
            $this->assertStringNotContainsString('<span>New Inhabitants</span>', $match[0]);
            $response->assertSee('href="'.route('barangay.rbi-updates.index').'">Open RBI Forms', false);
        }
    }
}
