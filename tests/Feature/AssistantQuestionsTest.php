<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, MigrationRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['local_ai.enabled' => false]);
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 1, 15));
    }

    public function test_month_queries_use_movement_dates_and_previous_year_boundary(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        $person = Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'first_name' => 'Private', 'last_name' => 'Example', 'sex' => 'Male', 'status' => Inhabitant::STATUS_ACTIVE]);
        foreach (['2025-12-02', '2025-11-02', '2026-01-02'] as $date) MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id, 'type' => 'out', 'movement_date' => $date]);
        $before = MigrationRecord::all()->toArray();
        foreach (['Show migration last month', 'Show migration December 2025', 'Show migration 2025-12'] as $question) {
            $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])->assertOk()
                ->assertJsonPath('facts.month', '2025-12')->assertJsonPath('facts.out_migration', 1)->assertJsonPath('facts.year', 2025);
        }
        $this->postJson(route('assistant.chat'), ['message' => 'migration January February 2026'])->assertOk()->assertJsonMissingPath('facts');
        $this->postJson(route('assistant.chat'), ['message' => 'migration 2026-13'])->assertOk()->assertJsonMissingPath('facts');
        $this->assertSame($before, MigrationRecord::all()->toArray());
        Http::assertNothingSent();
    }

    public function test_cebuano_population_and_coverage_keep_barangay_scope(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (['Pila ka tawo sa among barangay?', 'Pila ka bata?', 'Pila ka tigulang?', 'Pila ka pamilya ug household?', 'How many families are registered?'] as $question) {
            $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc');
        }
        $this->postJson(route('assistant.chat'), ['message' => 'Show population coverage'])->assertOk()->assertJsonCount(1, 'facts.coverage');
        $this->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo sa Canlupao?'])->assertOk()->assertJsonMissingPath('facts');
    }

    public function test_guidance_is_available_without_exposing_staff_records(): void
    {
        $resident = User::factory()->create();
        foreach (['Unsaon pag request ug certificate?', 'Unsaon pagbayad sa GCash?', 'Pila ang document fees?', 'What do request statuses mean?', 'Unsaon pag change sa password?', 'Explain family counting', 'Explain PWD counting', 'How to download PDF?'] as $question) {
            $response = $this->actingAs($resident)->postJson(route('assistant.chat'), ['message' => $question])->assertOk()->assertJsonMissingPath('facts');
            $this->assertStringNotContainsString('I can only help', $response->json('reply'));
        }
        $this->postJson(route('assistant.chat'), ['message' => 'What can I ask?'])->assertOk()->assertJsonMissingPath('facts');
        Http::assertNothingSent();
    }
}
