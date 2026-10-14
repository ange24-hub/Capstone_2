<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DocumentRequest;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant_ai.enabled' => true, 'assistant_ai.url' => 'http://ollama:11434', 'assistant_ai.model' => 'qwen2.5:1.5b', 'local_ai.enabled' => false]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 1, 15));
    }

    private function model(array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $plan = array_replace(['intent' => 'population', 'metrics' => ['residents'], 'statuses' => [], 'place' => '', 'confidence' => 0.95], $overrides);
        Http::fake([
            'ollama:11434/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:1.5b']]]),
            'ollama:11434/api/chat' => Http::response(['message' => ['content' => json_encode($plan)]]),
        ]);
    }

    private function staff(string $role = User::ROLE_BARANGAY): User
    {
        return User::factory()->create(['role' => $role, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
    }

    public function test_ai_understands_a_paraphrase_but_only_the_database_supplies_counts(): void
    {
        $user = $this->staff();
        $house = Household::create(['barangay_id' => $user->barangay_id, 'household_number' => '1']);
        Inhabitant::create(['barangay_id' => $user->barangay_id, 'household_id' => $house->id, 'first_name' => 'PrivateName', 'last_name' => 'NeverSent', 'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE]);
        $this->model();
        $answer = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Unsa kadaghan ang nagpuyo diri?', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('facts.residents', 1)->assertJsonPath('facts.scope', 'Barangay Looc')
            ->assertJsonPath('focused_metrics', ['residents'])->assertJsonPath('language', 'ceb')
            ->assertJsonPath('interpreted_question', 'Show population total');
        $this->assertStringContainsString('Rehistradong mga residente: 1', $answer->json('reply'));
        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($user) {
            if (! str_ends_with($request->url(), '/api/chat')) return false;
            $body = $request->body();
            return ! str_contains($body, 'PrivateName') && ! str_contains($body, $user->email)
                && ! str_contains($body, 'NeverSent') && $request['stream'] === false;
        });
    }

    public function test_model_cannot_discard_or_invent_a_barangay_scope(): void
    {
        $this->model(); // Even if it omits Canlupao, preserve the explicit place.
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Unsa kadaghan ang nagpuyo sa Canlupao?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('reply', 'You can only view reports for your assigned barangay.');
        $this->model(['place' => 'Looc']); // Never substitute a model-invented place.
        $this->actingAs($this->staff(User::ROLE_MUNICIPAL_LGU))->postJson(route('assistant.chat'), ['message' => 'Unsa kadaghan ang nagpuyo sa Canlupao?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('needs_clarification', true);
    }

    public function test_residents_and_unapproved_staff_cannot_gain_report_access_through_ai(): void
    {
        $this->model();
        $this->actingAs(User::factory()->create())->postJson(route('assistant.chat'), ['message' => 'Unsa kadaghan ang nagpuyo diri?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertSee('protected barangay records');
        $user = $this->staff();
        $user->update(['approval_status' => User::APPROVAL_PENDING]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Unsa kadaghan ang nagpuyo diri?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertSee('approved barangay account');
    }

    public function test_ai_preserves_relative_dates_and_rejects_conflicting_dates_and_places(): void
    {
        $this->model(['intent' => 'migration', 'metrics' => []]);
        $this->actingAs($this->staff(User::ROLE_MUNICIPAL_LGU))->postJson(route('assistant.chat'), ['message' => 'Kumusta ang pagbalhin sa Looc niaging bulana?'])
            ->assertOk()->assertJsonPath('facts.month', '2025-12')->assertJsonPath('facts.scope', 'Barangay Looc');
        $this->postJson(route('assistant.chat'), ['message' => 'Kumusta ang pagbalhin sa Looc September October 2026?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertSee('one valid reporting month');
        $this->postJson(route('assistant.chat'), ['message' => 'Kumusta ang pagbalhin sa Looc ug Canlupao?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertSee('one barangay per question');
        $this->postJson(route('assistant.chat'), ['message' => 'Kumusta ang pagbalhin sunod tuig?'])
            ->assertOk()->assertJsonMissingPath('facts')->assertSee('Forecasting is not enabled');
    }

    public function test_ai_document_status_reads_only_the_residents_latest_request(): void
    {
        $user = User::factory()->create();
        DocumentRequest::create(['resident_id' => $user->id, 'barangay_id' => $user->barangay_id, 'reference_number' => 'OWN-123', 'document_type' => DocumentRequest::TYPE_INDIGENCY, 'purpose' => 'Assistance', 'status' => DocumentRequest::STATUS_PENDING]);
        $other = User::factory()->create();
        DocumentRequest::create(['resident_id' => $other->id, 'barangay_id' => $other->barangay_id, 'reference_number' => 'OTHER-SECRET', 'document_type' => DocumentRequest::TYPE_INDIGENCY, 'purpose' => 'Assistance', 'status' => DocumentRequest::STATUS_READY]);
        $this->model(['intent' => 'document_status', 'metrics' => []]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Nahuman na ba akong gi-request?'])
            ->assertOk()->assertSee('OWN-123')->assertSee('Pending review')->assertDontSee('OTHER-SECRET');
    }

    public function test_ambiguous_questions_and_unknown_places_ask_for_details(): void
    {
        $this->model(['intent' => 'clarify', 'metrics' => [], 'confidence' => 0.3]);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Pila man tanan?', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('needs_clarification', true)->assertJsonMissingPath('facts')->assertSee('Palihog klaroha');
        $this->model(['place' => 'UnknownVillage']);
        $this->postJson(route('assistant.chat'), ['message' => 'Pila kabuok nagpuyo sa UnknownVillage?'])
            ->assertOk()->assertJsonPath('needs_clarification', true)->assertJsonMissingPath('facts');
    }

    public function test_model_failure_falls_back_and_backs_off_without_breaking_chat(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection refused');
        });
        $user = $this->staff();
        foreach (['Pila kabuok mga tawo?', 'Pila ka hamtong?'] as $question) {
            $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => $question])
                ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonStructure(['notice']);
        }
        $this->assertSame(1, $attempts);
    }

    public function test_invalid_model_output_falls_back_without_executing_or_showing_it(): void
    {
        $this->model(['sql' => 'DROP TABLE users', 'reply' => '<script>invented</script>']);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo?'])
            ->assertOk()->assertJsonPath('facts.residents', 0)->assertJsonStructure(['notice'])
            ->assertDontSee('DROP TABLE')->assertDontSee('invented');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_disabled_interpreter_uses_aliases_and_existing_followups_without_http(): void
    {
        config(['assistant_ai.enabled' => false]);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Pila ka hamtong?'])
            ->assertOk()->assertJsonPath('focused_metrics', ['adults'])->assertJsonMissingPath('notice');
        $this->postJson(route('assistant.chat'), ['message' => 'ug mga bata?', 'previous_question' => 'Show population adults Barangay Looc'])
            ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonPath('focused_metrics', ['children']);
        $this->actingAs($this->staff(User::ROLE_MUNICIPAL_LGU))->postJson(route('assistant.chat'), ['message' => 'ug mga tawo?', 'previous_question' => 'Show population seniors Barangay Looc'])
            ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonPath('focused_metrics', ['residents']);
        Http::assertNothingSent();
    }

    public function test_public_endpoints_and_cloud_models_are_rejected(): void
    {
        config(['assistant_ai.url' => 'https://external.example.com']);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo?'])
            ->assertOk()->assertJsonStructure(['notice']);
        config(['assistant_ai.url' => 'http://ollama:11434', 'assistant_ai.model' => 'model:cloud']);
        $this->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo?'])->assertOk()->assertJsonStructure(['notice']);
        Http::assertNothingSent();
    }

    public function test_missing_and_cloud_backed_models_do_not_receive_prompts(): void
    {
        Http::fake(['ollama:11434/api/tags' => Http::sequence()
            ->push(['models' => []])
            ->push(['models' => [['name' => 'qwen2.5:1.5b', 'remote_host' => 'cloud.example']]])]);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo?'])->assertOk()->assertJsonStructure(['notice']);
        Http::assertSentCount(1);
        Cache::flush();
        $this->postJson(route('assistant.chat'), ['message' => 'Pila ka tawo?'])->assertOk()->assertJsonStructure(['notice']);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
    }

    public function test_check_command_probes_the_configured_model_without_database_queries(): void
    {
        $this->model();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->artisan('assistant:check --probe')->expectsOutput('The model correctly understood the synthetic population question.')->assertSuccessful();
        $this->assertSame([], $queries);
    }

    public function test_ai_guidance_and_status_filters_use_supported_system_answers(): void
    {
        $user = User::factory()->create();
        $this->model(['intent' => 'request_document', 'metrics' => []]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Unsay buhaton para makakuha ug sertipiko?', 'language' => 'ceb'])
            ->assertOk()->assertSee('Kung approved na')->assertJsonPath('actions.0.url', route('resident.document-requests.create'));
        $this->model(['intent' => 'document_counts', 'metrics' => [], 'statuses' => ['ready']]);
        $this->postJson(route('assistant.chat'), ['message' => 'Ihap sa mga andam nang kuhaon nga papeles', 'language' => 'en'])
            ->assertOk()->assertSee('Ready for release: 0')->assertDontSee('Pending review:');
        $this->model(['intent' => 'document_counts', 'metrics' => [], 'statuses' => ['paid']]);
        $this->postJson(route('assistant.chat'), ['message' => 'Ihap sa mga andam nang kuhaon nga papeles'])
            ->assertOk()->assertJsonPath('needs_clarification', true);
    }

    public function test_malformed_model_json_falls_back_and_unsupported_topics_are_not_generated(): void
    {
        Http::fake([
            'ollama:11434/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:1.5b']]]),
            'ollama:11434/api/chat' => Http::response(['message' => ['content' => '{not valid JSON']]),
        ]);
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'Show population summary'])
            ->assertOk()->assertJsonPath('facts.scope', 'Barangay Looc')->assertJsonStructure(['notice']);
        Cache::flush();
        $this->model(['intent' => 'unsupported', 'metrics' => []]);
        $this->postJson(route('assistant.chat'), ['message' => 'Tell me tomorrow\'s weather'])
            ->assertOk()->assertJsonMissingPath('facts')->assertJsonPath('blocked_reason', 'outside_system');
    }

    public function test_guests_do_not_reach_ollama(): void
    {
        $this->postJson(route('assistant.chat'), ['message' => 'How many people live here?'])->assertUnauthorized();
        Http::assertNothingSent();
    }
}
