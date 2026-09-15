<?php

namespace Tests\Feature;

use App\Models\{Barangay, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['local_ai.enabled' => false]);
        $this->travelTo(now()->setDate(2026, 1, 15));
    }

    public function test_relative_followup_keeps_area_and_resolves_year_boundary(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $first = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Show Looc migration January 2026'])->assertOk();
        $next = $this->postJson(route('assistant.chat'), ['message' => 'ug last month?', 'previous_question' => $first->json('previous_question')])
            ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonPath('facts.month', '2025-12');
        $this->postJson(route('assistant.chat'), ['message' => 'and November?', 'previous_question' => $next->json('previous_question')])
            ->assertOk()->assertJsonPath('facts.month', '2025-11');
        $this->postJson(route('assistant.chat'), ['message' => 'download PDF', 'previous_question' => $next->json('previous_question')])
            ->assertOk()->assertJsonPath('actions.1.url', route('reports.migration.pdf', ['barangay_id' => Barangay::where('name', 'Looc')->value('id'), 'year' => 2025]));
    }

    public function test_context_is_not_authorization_and_unrelated_questions_clear_it(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'last month', 'previous_question' => 'Show migration Barangay Canlupao 2026-01'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('previous_question', null);
        $this->postJson(route('assistant.chat'), ['message' => 'What is my account status?', 'previous_question' => 'Show migration Barangay Looc 2026'])
            ->assertOk()->assertJsonPath('previous_question', null)->assertJsonMissingPath('interpreted_question');
        $this->postJson(route('assistant.chat'), ['message' => 'last month'])->assertOk()->assertJsonMissingPath('facts');
        $resident = User::factory()->create();
        $this->actingAs($resident)->postJson(route('assistant.chat'), ['message' => 'download PDF', 'previous_question' => 'Show population Barangay Looc'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('previous_question', null);
        $this->postJson(route('assistant.chat'), ['message' => 'last month', 'previous_question' => str_repeat('a', 501)])->assertUnprocessable();
    }

    public function test_population_conversation_retains_scope_metric_and_pdf_across_followups(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $answer = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Pila ka pamilya sa Looc?'])->assertOk();
        foreach (['Ug senior?' => 'seniors', 'Pila pud ang PWD?' => 'pwd', 'ug lalaki ug babaye?' => 'male'] as $question => $metric) {
            $answer = $this->postJson(route('assistant.chat'), ['message' => $question, 'previous_question' => $answer->json('previous_question')])
                ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonPath('focused_metrics.0', $metric);
        }
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'how about Canlupao?', 'previous_question' => $answer->json('previous_question')])
            ->assertOk()->assertJsonPath('facts.scope', 'Barangay Canlupao')->assertJsonPath('focused_metrics.0', 'male');
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'download PDF', 'previous_question' => $answer->json('previous_question')])
            ->assertOk()->assertJsonPath('actions.1.url', route('reports.population.pdf', ['barangay_id' => Barangay::where('name', 'Canlupao')->value('id')]));
        $this->postJson(route('assistant.chat'), ['message' => 'full summary', 'previous_question' => $answer->json('previous_question')])
            ->assertOk()->assertJsonMissingPath('focused_metrics')->assertJsonPath('facts.scope', 'Barangay Canlupao');
    }

    public function test_area_switching_cannot_escape_barangay_permissions_and_missing_context_is_explained(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'sa Canlupao?', 'previous_question' => 'Show population seniors Barangay Looc'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('previous_question', null);
        $response = $this->postJson(route('assistant.chat'), ['message' => 'ug senior?'])->assertOk()->assertJsonMissingPath('facts');
        $this->assertStringContainsString('complete question', $response->json('reply'));
        $this->postJson(route('assistant.chat'), ['message' => 'sa NotARealPlace?', 'previous_question' => 'Show population seniors Barangay Looc'])
            ->assertOk()->assertJsonMissingPath('facts');
        $user->update(['approval_status' => User::APPROVAL_PENDING]);
        foreach (['Summarize submitted RBI forms', 'Show pending resident approvals', 'Summarize our registry'] as $question) {
            $response = $this->postJson(route('assistant.chat'), ['message' => $question])->assertOk();
            $this->assertStringContainsString('must be approved', $response->json('reply'));
        }
    }
}
