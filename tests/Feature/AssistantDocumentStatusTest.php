<?php

namespace Tests\Feature;

use App\Models\{Barangay, DocumentRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantDocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(User $resident, string $reference, string $status, string $payment = 'unpaid'): void
    {
        DocumentRequest::create(['resident_id' => $resident->id, 'barangay_id' => $resident->barangay_id,
            'reference_number' => $reference, 'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Test', 'status' => $status, 'payment_status' => $payment, 'amount_due' => 50]);
    }

    public function test_resident_latest_status_is_not_confused_with_paid_and_does_not_expose_other_requests(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $resident = User::factory()->create(['barangay_id' => $area->id]);
        $other = User::factory()->create(['barangay_id' => $area->id]);
        $this->requestFor($resident, 'OWN', 'pending', 'paid');
        $this->requestFor($other, 'OTHER-SECRET', 'ready');
        $before = DocumentRequest::all()->toArray();
        $response = $this->actingAs($resident)->postJson(route('assistant.chat'), ['message' => 'Ready na ba akong request?'])->assertOk();
        $this->assertStringContainsString('Pending review', $response->json('reply'));
        $this->assertStringContainsString('Payment verified', $response->json('reply'));
        $this->assertStringNotContainsString('OTHER-SECRET', $response->getContent());
        $response = $this->postJson(route('assistant.chat'), ['message' => 'Pila ang ready document requests?'])->assertOk();
        $this->assertStringContainsString('Ready for release: 0', $response->json('reply'));
        $this->assertSame($before, DocumentRequest::all()->toArray());
    }

    public function test_staff_counts_are_barangay_scoped_and_payment_filters_are_distinct(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $other = Barangay::where('name', 'Canlupao')->firstOrFail();
        $resident = User::factory()->create(['barangay_id' => $area->id]);
        $outside = User::factory()->create(['barangay_id' => $other->id]);
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->requestFor($resident, 'OWN-1', 'pending');
        $this->requestFor($resident, 'OWN-2', 'ready', 'paid');
        $this->requestFor($outside, 'OTHER', 'pending');
        foreach (['Pila ang pending document requests?' => 'Pending review: 1', 'Show unpaid payment counts' => 'Awaiting GCash payment: 1', 'Show paid payment counts' => 'Payment verified: 1'] as $question => $expected) {
            $response = $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => $question])->assertOk();
            $this->assertStringContainsString($expected, $response->json('reply'));
        }
        $staff->update(['approval_status' => User::APPROVAL_PENDING]);
        $this->postJson(route('assistant.chat'), ['message' => 'Show payment status counts'])->assertOk()->assertJsonPath('actions', []);
        $this->postJson(route('assistant.chat'), ['message' => 'Show document requests'])->assertOk()->assertJsonPath('actions', []);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)->postJson(route('assistant.chat'), ['message' => 'Show pending document requests'])->assertOk()->assertJsonPath('actions', []);
    }
}
