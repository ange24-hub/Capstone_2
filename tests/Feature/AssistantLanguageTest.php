<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['local_ai.enabled' => false]);
        Http::preventStrayRequests();
    }

    public function test_typed_language_preference_persists_and_can_switch_back(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_RESIDENT, 'name' => 'Male Households']);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Can you reply in Bisaya?', 'language' => 'en'])
            ->assertOk()->assertJsonPath('language', 'ceb')->assertSee('mag-Bisaya');
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'What is my account status?'])
            ->assertOk()->assertJsonPath('language', 'ceb');
        $this->assertStringContainsString('Naka-sign in ka isip Male Households', $answer->json('reply'));
        $this->postJson(route('assistant.chat'), ['message' => 'English please', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('language', 'en');
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'What is my account status?'])->assertOk();
        $this->assertStringContainsString('You are signed in as Male Households', $answer->json('reply'));
        Http::assertNothingSent();
    }

    public function test_combined_language_instruction_keeps_verified_counts_and_follow_up_context(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'first_name' => 'Private', 'last_name' => 'Resident', 'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE]);
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $locale = app()->getLocale();
        $answer = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Answer in Bisaya: Show population summary'])
            ->assertOk()->assertJsonPath('language', 'ceb')->assertJsonPath('facts.residents', 1)
            ->assertJsonPath('facts.female', 1)->assertJsonPath('mode', 'database_summary');
        $this->assertStringContainsString('Rehistradong mga residente: 1', $answer->json('reply'));
        $this->assertStringContainsString('Babaye: 1', $answer->json('reply'));
        $this->assertStringNotContainsString('Private', $answer->json('reply'));
        $this->assertSame($locale, app()->getLocale());
        $this->postJson(route('assistant.chat'), ['message' => 'ug babaye?', 'previous_question' => $answer->json('previous_question')])
            ->assertOk()->assertJsonPath('language', 'ceb')->assertJsonPath('focused_metrics', ['female']);
        Http::assertNothingSent();
    }

    public function test_changing_language_alone_preserves_follow_up_topic(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $context = 'Show migration Barangay Looc 2026-09';
        $answer = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Bisaya lang', 'previous_question' => $context])
            ->assertOk()->assertJsonPath('previous_question', $context);
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'ug last month?', 'previous_question' => $answer->json('previous_question')])
            ->assertOk()->assertJsonPath('language', 'ceb');
        $this->assertStringContainsString('Mga pagsulod (in-migration)', $answer->json('reply'));
    }

    public function test_selector_and_translated_suggestions_work_without_a_model(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $answer = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'How do I request a document?', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('language', 'ceb');
        $this->assertStringContainsString('Kung approved na', $answer->json('reply'));
        $this->assertSame(route('resident.document-requests.create'), $answer->json('actions.0.url'));
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'Ipakita ang akong document requests'])->assertOk();
        $this->assertStringContainsString('Aduna kay 0 ka document requests', $answer->json('reply'));
        $answer = $this->postJson(route('assistant.chat'), ['message' => 'unsa ang pasabot sa request statuses?'])->assertOk();
        $this->assertStringContainsString('naghulat nga susihon sa barangay', $answer->json('reply'));
        $this->postJson(route('assistant.chat'), ['message' => 'What is my account status?', 'language' => 'en'])
            ->assertOk()->assertJsonPath('language', 'en');
        $this->postJson(route('assistant.chat'), ['message' => 'Hello', 'language' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('language');
        Http::assertNothingSent();
    }

    public function test_bisaya_preserves_barangay_permissions(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Show Canlupao population', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('reply', 'Ang reports sa imong na-assign nga barangay lamang ang imong makita.')
            ->assertJsonMissingPath('facts')->assertJsonPath('actions', []);
        Http::assertNothingSent();
    }

    public function test_language_preference_is_scoped_to_the_authenticated_user(): void
    {
        $first = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $second = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($first)->postJson(route('assistant.chat'), ['message' => 'Bisaya lang'])->assertOk();
        $this->actingAs($second)->postJson(route('assistant.chat'), ['message' => 'What is my account status?'])
            ->assertOk()->assertJsonPath('language', 'en');
    }

    public function test_local_ai_does_not_add_an_english_narrative_to_bisaya_reports(): void
    {
        config(['local_ai.enabled' => true]);
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $house = Household::create(['barangay_id' => $area->id, 'household_number' => '1']);
        Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $house->id, 'first_name' => 'Private', 'last_name' => 'Resident', 'sex' => 'Female', 'status' => Inhabitant::STATUS_ACTIVE]);
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Show population summary', 'language' => 'ceb'])
            ->assertOk()->assertJsonPath('mode', 'database_summary')->assertJsonPath('language', 'ceb');
        Http::assertNothingSent();
    }
}
