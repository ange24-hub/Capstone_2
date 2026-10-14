<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant, User};
use App\Services\{AssistantIntentInterpreter, RbimAssistant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantPopulationNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant_ai.enabled' => true, 'local_ai.enabled' => true]);
        Http::preventStrayRequests();
    }

    private function staff(string $role = User::ROLE_BARANGAY): User
    {
        return User::factory()->create(['role' => $role, 'barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
    }

    private function resident(string $name, string $remarks = '', array $values = [], string $barangay = 'Looc'): Inhabitant
    {
        $areaId = Barangay::where('name', $barangay)->value('id');
        $household = Household::firstOrCreate(['barangay_id' => $areaId, 'household_number' => '1']);
        return Inhabitant::create(array_replace(['barangay_id' => $areaId, 'household_id' => $household->id,
            'first_name' => $name, 'last_name' => 'Example', 'sex' => 'Female', 'remarks' => $remarks,
            'status' => Inhabitant::STATUS_ACTIVE, 'complete_address' => 'PRIVATE ADDRESS', 'contact_number' => 'PRIVATE CONTACT'], $values));
    }

    public function test_pwd_names_use_existing_markers_and_scope_with_ai_enabled_or_disabled(): void
    {
        $staff = $this->staff();
        $this->resident('Alice', 'PWD');
        $this->resident('Bob', 'Person with disability', ['status' => Inhabitant::STATUS_INACTIVE]);
        $this->resident('ExcludedNegative', 'NOT PWD');
        $this->resident('ExcludedUncertain', 'POSSIBLE PWD');
        $this->resident('OtherBarangay', 'PWD', [], 'Canlupao');
        $before = Inhabitant::orderBy('id')->get()->toArray();
        foreach ([true, false] as $enabled) {
            config(['assistant_ai.enabled' => $enabled]);
            foreach (['hatagi kog names sa mga pwd', 'Show names of PWD residents', 'Ipakita ang mga ngalan sa PWD', 'Lista sa PWD'] as $question) {
                $response = $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])
                    ->assertOk()->assertJsonPath('mode', 'database_list')->assertJsonPath('facts.total_records', 2)
                    ->assertJsonPath('previous_question', null)->assertSee('Alice Example')->assertSee('Bob Example')
                    ->assertDontSee('Excluded')->assertDontSee('OtherBarangay')
                    ->assertDontSee('PRIVATE ADDRESS')->assertDontSee('PRIVATE CONTACT');
                $this->assertStringContainsString('section=pwd', $response->json('actions.0.url'));
            }
        }
        $direct = app(RbimAssistant::class)->respond($staff, 'Show PWD names');
        $this->assertSame(2, $direct['facts']['total_records']);
        $interpreted = app(AssistantIntentInterpreter::class)->interpret($staff, 'Show PWD names');
        $this->assertSame('database_list', $interpreted['answer']['mode']);
        Http::assertNothingSent();
        $this->assertSame($before, Inhabitant::orderBy('id')->get()->toArray());
    }

    public function test_senior_names_use_age_or_recognized_remarks_without_double_counting(): void
    {
        $this->resident('AgeAndRemark', 'SC', ['birth_date' => now()->subYears(70)->toDateString()]);
        $this->resident('AgeOnly', '', ['birth_date' => now()->subYears(60)->toDateString()]);
        $this->resident('RemarkOnly', 'Senior citizen');
        $this->resident('TooYoung', '', ['birth_date' => now()->subYears(59)->toDateString()]);
        $this->resident('Uncertain', 'SC?');
        $this->resident('OtherBarangay', 'SC', [], 'Canlupao');
        $staff = $this->staff();
        foreach (['hatagi kog names sa senior citizens', 'Show senior citizen names', 'Kinsa ang mga tigulang sa among barangay?'] as $question) {
            $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])
                ->assertOk()->assertJsonPath('facts.category', 'seniors')->assertJsonPath('facts.total_records', 3)
                ->assertSee('AgeAndRemark Example')->assertSee('AgeOnly Example')->assertSee('RemarkOnly Example')
                ->assertDontSee('TooYoung')->assertDontSee('Uncertain')->assertDontSee('OtherBarangay');
        }
        Http::assertNothingSent();
    }

    public function test_residents_and_unapproved_or_unassigned_staff_cannot_read_name_lists(): void
    {
        $this->resident('Confidential', 'PWD SC');
        $resident = User::factory()->create(['barangay_id' => Barangay::where('name', 'Looc')->value('id')]);
        $pending = $this->staff();
        $pending->update(['approval_status' => User::APPROVAL_PENDING]);
        $unassigned = $this->staff();
        $unassigned->update(['barangay_id' => null]);
        foreach ([$resident, $pending, $unassigned] as $user) {
            foreach (['Show PWD names', 'Show senior citizen names'] as $question) {
                $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => $question])
                    ->assertOk()->assertJsonPath('blocked_reason', 'staff_only')->assertJsonPath('actions', [])
                    ->assertJsonMissingPath('facts')->assertDontSee('Confidential');
            }
        }
        Http::assertNothingSent();
    }

    public function test_municipal_lists_and_links_retain_the_selected_barangay(): void
    {
        $this->resident('LocalOnly', 'PWD');
        $this->resident('SelectedOnly', 'PWD', [], 'Canlupao');
        $user = $this->staff(User::ROLE_MUNICIPAL_LGU);
        $response = $this->actingAs($user)->postJson(route('assistant.chat'), ['message' => 'Show PWD names in Canlupao'])
            ->assertOk()->assertJsonPath('facts.total_records', 1)->assertSee('SelectedOnly Example')->assertDontSee('LocalOnly');
        $this->get($response->json('actions.0.url'))->assertOk()->assertSee('SelectedOnly')->assertDontSee('LocalOnly');
        $this->postJson(route('assistant.chat'), ['message' => 'Show PWD names in the whole municipality'])
            ->assertOk()->assertJsonPath('facts.total_records', 2)->assertSee('LocalOnly Example (Looc)')->assertSee('SelectedOnly Example (Canlupao)');
        Http::assertNothingSent();
    }

    public function test_scope_and_unsupported_filters_do_not_become_broader_name_lists(): void
    {
        $this->resident('Confidential', 'PWD SC');
        $staff = $this->staff();
        foreach (['Show PWD names in Canlupao', 'Show senior names in Tokyo', 'Show PWD names for Tokyo',
            'Show PWD names last month', 'Show female PWD names', 'Show active senior names',
            'Show PWD and senior citizen names', 'Give PWD names and phone numbers', 'Show PWD names in the whole municipality'] as $question) {
            $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])
                ->assertOk()->assertJsonMissingPath('facts')->assertDontSee('Confidential');
        }
        Http::assertNothingSent();
    }

    public function test_empty_and_large_lists_are_honest_and_bounded(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => 'Show PWD names'])
            ->assertOk()->assertJsonPath('facts.total_records', 0)->assertSee('No matching records are encoded');
        foreach (range(1, 51) as $index) $this->resident(sprintf('Member%02d', $index), 'PWD');
        $this->postJson(route('assistant.chat'), ['message' => 'Show PWD names'])
            ->assertOk()->assertJsonPath('facts.total_records', 51)->assertJsonPath('facts.displayed_records', 50)
            ->assertSee('first 50 of 51')->assertDontSee('Member51');
        Http::assertNothingSent();
    }

    public function test_cebuano_name_lists_translate_labels_without_changing_record_names(): void
    {
        $this->resident('Alice', 'PWD');
        $this->actingAs($this->staff())->postJson(route('assistant.chat'), ['message' => 'hatagi kog names sa mga pwd', 'language' => 'ceb'])
            ->assertOk()->assertSee('Natala nga mga ngalan')->assertSee('Alice Example');
        Http::assertNothingSent();
    }
}
