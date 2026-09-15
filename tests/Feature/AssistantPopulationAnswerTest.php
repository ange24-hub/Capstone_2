<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantPopulationAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_focused_answers_match_report_rules_and_do_not_call_the_model_or_change_records(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 12));
        config(['local_ai.enabled' => true]);
        Http::preventStrayRequests();
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        foreach ([['1960-01-01', 'SC PWD'], ['2015-01-01', null], [null, 'SC']] as $index => [$birth, $remarks]) {
            Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'family_number' => $index === 2 ? '1.1' : '1',
                'first_name' => 'Private', 'last_name' => 'Resident', 'sex' => 'Female', 'birth_date' => $birth, 'remarks' => $remarks, 'status' => Inhabitant::STATUS_ACTIVE]);
        }
        $single = Household::create(['barangay_id' => $area->id, 'household_number' => '2']);
        Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $single->id, 'first_name' => 'Private', 'last_name' => 'Single', 'sex' => 'Male', 'birth_date' => '1990-01-01', 'status' => Inhabitant::STATUS_ACTIVE]);
        $before = Inhabitant::all()->toArray();
        foreach ([
            ['Pila ka senior?', 'seniors', 'Senior citizens: 2', 'Households:'],
            ['Pila ka PWD?', 'pwd', 'PWD markers: 1', 'Senior citizens:'],
            ['Pila ka bata?', 'children', 'Children aged 0-17: 1', 'Male:'],
            ['How many adults?', 'adults', 'Adults aged 18+: 2', 'PWD markers:'],
            ['Pila ka pamilya?', 'families', 'Identified families: 3', 'Male:'],
        ] as [$question, $metric, $expected, $unrelated]) {
            $response = $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])->assertOk()
                ->assertJsonPath('focused_metrics.0', $metric)->assertJsonPath('mode', 'database_summary')
                ->assertJsonPath('actions.1.url', route('reports.population.pdf', ['barangay_id' => $area->id]));
            $this->assertStringContainsString($expected, $response->json('reply'));
            $this->assertStringNotContainsString($unrelated, $response->json('reply'));
            $this->assertStringContainsString('completeness unverified', $response->json('reply'));
        }
        $response = $this->postJson(route('assistant.chat'), ['message' => 'Pila ka pamilya ug household?'])->assertOk()->assertJsonCount(2, 'focused_metrics');
        $this->assertStringContainsString('Single-person households', $response->json('reply'));
        $this->assertStringContainsString('may be incomplete', $response->json('reply'));
        $this->assertSame($before, Inhabitant::all()->toArray());
        Http::assertNothingSent();
    }

    public function test_empty_counts_remain_qualified_and_full_summary_remains_available(): void
    {
        config(['local_ai.enabled' => false]);
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $response = $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => 'Pila ka senior?'])->assertOk();
        $this->assertStringContainsString('does not mean zero population', $response->json('reply'));
        $this->postJson(route('assistant.chat'), ['message' => 'Show population summary'])->assertOk()->assertJsonMissingPath('focused_metrics')->assertJsonPath('facts.seniors', 0);
        $this->postJson(route('assistant.chat'), ['message' => 'Pila ka senior sa Canlupao?'])->assertOk()->assertJsonMissingPath('facts');
    }
}
