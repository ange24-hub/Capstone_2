<?php

namespace Tests\Feature;

use App\Models\{Barangay, DocumentRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayDocumentRequestPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_scoped_ordered_and_keep_full_pending_totals(): void
    {
        $barangay = Barangay::firstOrFail();
        $otherBarangay = Barangay::whereKeyNot($barangay->id)->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $resident = User::factory()->create(['barangay_id' => $barangay->id]);
        $createdAt = now()->subDay();

        foreach (range(1, 8) as $index) {
            DocumentRequest::create([
                'resident_id' => $resident->id,
                'barangay_id' => $index === 8 ? $otherBarangay->id : $barangay->id,
                'reference_number' => 'BRGY-PAGE-'.$index,
                'document_type' => DocumentRequest::TYPE_INDIGENCY,
                'purpose' => 'Employment assistance',
                'status' => $index === 7 ? DocumentRequest::STATUS_COMPLETED : DocumentRequest::STATUS_PENDING,
                'payment_status' => DocumentRequest::PAYMENT_NOT_REQUIRED,
            ])->forceFill(['created_at' => $createdAt])->save();
        }

        $this->actingAs($secretary)->get(route('barangay.document-requests.index'))
            ->assertOk()->assertSee('BRGY-PAGE-7')->assertSee('BRGY-PAGE-3')
            ->assertDontSee('BRGY-PAGE-2')->assertDontSee('BRGY-PAGE-8')
            ->assertSee('document_page=2#document-requests', false)
            ->assertSee('aria-current="page"', false)
            ->assertViewHas('pendingDocuments', 6)
            ->assertViewHas('barangayDocumentRequests', fn ($requests) => $requests->total() === 7 && $requests->count() === 5);

        $this->get(route('barangay.document-requests.index', ['document_page' => 2]))
            ->assertOk()->assertSee('BRGY-PAGE-2')->assertSee('BRGY-PAGE-1')
            ->assertDontSee('BRGY-PAGE-3')->assertDontSee('BRGY-PAGE-8')
            ->assertViewHas('pendingDocuments', 6)
            ->assertViewHas('barangayDocumentRequests', fn ($requests) => $requests->total() === 7 && $requests->count() === 2);

        $this->get(route('dashboard.barangay'))
            ->assertOk()->assertViewHas('pendingDocuments', 6);

        $this->get(route('barangay.document-requests.index', ['document_page' => 99]))
            ->assertOk()->assertSee('No requests on this page')->assertSee('Return to the first page');
    }

    public function test_empty_queue_has_no_pagination_controls(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::firstOrFail()->id]);

        $this->actingAs($secretary)->get(route('barangay.document-requests.index'))
            ->assertOk()->assertSee('No document requests received')
            ->assertDontSee('aria-label="Document request pages"', false)
            ->assertViewHas('pendingDocuments', 0);
    }
}
