<?php

namespace Tests\Feature;

use App\Models\{Barangay, User};
use App\Services\AssistantGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_suggested_question_has_a_supported_response_for_each_role(): void
    {
        config(['local_ai.enabled' => false]);
        Http::preventStrayRequests();
        foreach ([User::ROLE_RESIDENT, User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU] as $role) {
            $user = User::factory()->create(['role' => $role, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
            $this->actingAs($user);
            foreach (AssistantGuide::questions($user) as $question) {
                $response = $this->postJson(route('assistant.chat'), ['message' => $question])->assertOk()->assertJsonPath('scope', 'RBIM system only');
                $this->assertStringNotContainsString('I can only help', $response->json('reply'), $role.': '.$question);
                if ($role === User::ROLE_RESIDENT) $response->assertJsonMissingPath('facts');
            }
            $this->blade('@include("assistant.widget")')->assertSee('Explore questions')->assertSee('New conversation');
        }
        Http::assertNothingSent();
    }
}
