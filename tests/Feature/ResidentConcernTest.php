<?php

namespace Tests\Feature;

use App\Models\{Barangay, ResidentConcern, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class ResidentConcernTest extends TestCase
{
    use RefreshDatabase;

    private function actors(): array
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        return [User::factory()->create(['barangay_id' => $area->id]),
            User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]),
            User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $other->id])];
    }

    private function concern(User $resident, array $attributes = []): ResidentConcern
    {
        return ResidentConcern::create(array_merge(['reference' => 'CON-'.\Illuminate\Support\Str::ulid(),
            'resident_id' => $resident->id, 'barangay_id' => $resident->barangay_id,
            'title' => 'PRIVATE-CONCERN-TITLE', 'description' => 'PRIVATE DESCRIPTION of blocked drainage near the hall.',
            'category' => 'roads', 'status' => 'submitted'], $attributes));
    }

    public function test_resident_submission_uses_account_scope_private_attachment_and_history(): void
    {
        Storage::fake('local'); Http::fake();
        [$resident, $staff, $other] = $this->actors();
        $this->actingAs($resident)->get(route('concerns.create'))->assertOk();
        $this->post(route('concerns.store'), ['title' => 'Blocked drainage', 'description' => 'The drainage near the barangay hall is blocked.',
            'category' => 'roads', 'location' => 'Near the hall', 'barangay_id' => $other->barangay_id,
            'resident_id' => $other->id, 'status' => 'resolved', 'ai_category' => 'water',
            'attachment' => UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf')])->assertRedirect();
        $concern = ResidentConcern::firstOrFail();
        $this->assertSame((int) $resident->barangay_id, (int) $concern->barangay_id);
        $this->assertSame((int) $resident->id, (int) $concern->resident_id);
        $this->assertSame('submitted', $concern->status);
        $this->assertNull($concern->ai_category);
        $this->assertSame(1, $concern->updates()->count());
        Storage::disk('local')->assertExists($concern->attachment_path);
        $this->get(route('concerns.attachment', $concern))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($staff)->get(route('concerns.attachment', $concern))->assertOk();
        $this->actingAs($other)->get(route('concerns.attachment', $concern))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_access_is_owner_or_assigned_barangay_and_municipal_is_aggregate_only(): void
    {
        [$resident, $staff, $other] = $this->actors();
        $concern = $this->concern($resident);
        $this->get(route('concerns.index'))->assertRedirect(route('login'));
        $peer = User::factory()->create(['barangay_id' => $resident->barangay_id]);
        $this->actingAs($peer)->get(route('concerns.show', $concern))->assertForbidden();
        $this->get(route('concerns.index'))->assertOk()->assertDontSee($concern->title);
        $this->actingAs($other)->get(route('concerns.show', $concern))->assertForbidden();
        $this->get(route('concerns.index'))->assertOk()->assertDontSee($concern->title);
        $this->actingAs($staff)->get(route('concerns.index'))->assertOk()->assertSee($concern->title);
        $this->get(route('concerns.create'))->assertForbidden();
        $this->get(route('concerns.summary'))->assertForbidden();
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)->get(route('concerns.show', $concern))->assertForbidden();
        $this->get(route('concerns.summary'))->assertOk()->assertDontSee($concern->title)->assertDontSee($resident->name)->assertDontSee($concern->description);
        $pending = User::factory()->create(['approval_status' => User::APPROVAL_PENDING, 'barangay_id' => $resident->barangay_id]);
        $this->actingAs($pending)->get(route('concerns.index'))->assertRedirect(route('approval.pending'));
        $unassigned = User::factory()->create(['barangay_id' => null]);
        $this->actingAs($unassigned)->get(route('concerns.index'))->assertForbidden();
    }

    public function test_staff_updates_are_audited_private_notes_hidden_and_stale_changes_rejected(): void
    {
        [$resident, $staff, $other] = $this->actors();
        $concern = $this->concern($resident);
        $data = ['status' => 'under_review', 'category' => 'water', 'message' => 'We are reviewing the reported issue.', 'internal_note' => 'PRIVATE STAFF NOTE', 'version' => 1];
        $this->actingAs($resident)->put(route('concerns.update', $concern), $data)->assertForbidden();
        $this->actingAs($other)->put(route('concerns.update', $concern), $data)->assertForbidden();
        $this->actingAs($staff)->put(route('concerns.update', $concern), array_merge($data, ['status' => 'resolved']))->assertSessionHasErrors('status');
        $this->put(route('concerns.update', $concern), $data)->assertRedirect();
        $concern->refresh();
        $this->assertSame(2, $concern->version);
        $this->assertSame('water', $concern->category);
        $this->assertSame('under_review', $concern->status);
        $this->get(route('concerns.show', $concern))->assertOk()->assertSee('PRIVATE STAFF NOTE');
        $this->put(route('concerns.update', $concern), $data)->assertSessionHasErrors('version');
        $response = $this->actingAs($resident)->get(route('concerns.show', $concern))->assertOk()->assertSee($data['message'])->assertDontSee('PRIVATE STAFF NOTE');
        $this->assertArrayNotHasKey('internal_note', $response->viewData('updates')->first()->getAttributes());
        $this->post(route('concerns.reply', $concern), ['message' => 'The water is still overflowing.'])->assertRedirect();
        $this->assertSame(3, $concern->fresh()->version);
        $this->assertSame(2, $concern->updates()->count());
        $this->actingAs($staff)->put(route('concerns.update', $concern), array_merge($data, ['version' => 3, 'status' => 'resolved']))->assertRedirect();
        $this->put(route('concerns.update', $concern), array_merge($data, ['version' => 4, 'status' => 'closed']))->assertRedirect();
        $this->actingAs($resident)->post(route('concerns.reply', $concern), ['message' => 'Please reopen this concern.'])->assertSessionHasErrors('message');
        $this->actingAs($staff)->put(route('concerns.update', $concern), array_merge($data, ['version' => 5]))->assertRedirect();
        $this->assertSame('under_review', $concern->fresh()->status);
    }

    public function test_validation_and_summary_filters_do_not_expose_raw_records(): void
    {
        [$resident, $staff, $other] = $this->actors();
        Storage::fake('local');
        $this->actingAs($resident)->post(route('concerns.store'), ['title' => '', 'description' => 'short', 'category' => 'invented',
            'attachment' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertSessionHasErrors(['title', 'description', 'category', 'attachment']);
        $this->assertDatabaseCount('resident_concerns', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->get(route('concerns.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->concern($resident);
        $this->concern($resident, ['status' => 'closed', 'created_at' => now()->subDays(50)]);
        $this->concern($other, ['status' => 'resolved']);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $r = $this->actingAs($municipal)->get(route('concerns.summary'))->assertOk()->viewData('rows');
        $this->assertSame(2, (int) $r->sum('total'));
        $r = $this->get(route('concerns.summary', ['days' => 90, 'barangay_id' => $resident->barangay_id]))->assertOk()->viewData('rows');
        $this->assertSame(2, (int) $r->sum('total'));
        $this->assertSame(1, (int) $r->where('status', 'closed')->sum('total'));
        $this->get(route('concerns.summary', ['days' => 999]))->assertSessionHasErrors('days');
    }

    public function test_local_ai_suggestion_requires_staff_and_never_auto_applies_category_or_status(): void
    {
        [$resident, $staff, $other] = $this->actors();
        $concern = $this->concern($resident);
        config(['local_ai.enabled' => true, 'local_ai.url' => 'http://127.0.0.1:11434', 'local_ai.model' => 'local-test']);
        Http::fake(['*/api/tags' => Http::response(['models' => [['name' => 'local-test']]]),
            '*/api/chat' => Http::sequence()->push(['message' => ['content' => '{"category":"water"}']])->push(['message' => ['content' => '{"category":"invented"}']])]);
        $this->actingAs($resident)->post(route('concerns.suggest', $concern))->assertForbidden();
        $this->actingAs($other)->post(route('concerns.suggest', $concern))->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($staff)->post(route('concerns.suggest', $concern))->assertRedirect();
        $concern->refresh();
        $this->assertSame('water', $concern->ai_category);
        $this->assertSame('roads', $concern->category);
        $this->assertSame('submitted', $concern->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/chat') && !str_contains(json_encode($request->data()), $resident->email));
        $this->post(route('concerns.suggest', $concern))->assertSessionHasErrors('ai');
        $this->assertSame(2, $concern->fresh()->version);
    }
}
