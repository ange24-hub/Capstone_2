<?php

namespace Tests\Feature;

use App\Models\{Barangay, DocumentRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentRequestPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_are_paginated_scoped_and_totals_include_every_page(): void
    {
        $barangay = Barangay::firstOrFail();
        $resident = User::factory()->create(['barangay_id' => $barangay->id]);
        $other = User::factory()->create(['barangay_id' => $barangay->id]);
        $createdAt = now()->subDay();
        foreach (range(1, 8) as $index) {
            DocumentRequest::create([
                'resident_id' => $index === 8 ? $other->id : $resident->id,
                'barangay_id' => $barangay->id,
                'reference_number' => 'PAGE-REQUEST-'.$index,
                'document_type' => DocumentRequest::TYPE_INDIGENCY,
                'purpose' => 'Pagination verification',
                'status' => $index <= 2 ? DocumentRequest::STATUS_COMPLETED : DocumentRequest::STATUS_PENDING,
                'payment_status' => DocumentRequest::PAYMENT_NOT_REQUIRED,
            ])->forceFill(['created_at' => $createdAt])->save();
        }

        $first = $this->actingAs($resident)->get(route('resident.document-requests.index'));
        $first->assertOk()->assertSee('PAGE-REQUEST-7')->assertSee('PAGE-REQUEST-3')
            ->assertDontSee('PAGE-REQUEST-2')->assertDontSee('PAGE-REQUEST-8')
            ->assertSee('page=2#request-history', false)
            ->assertViewHas('documentRequests', fn ($requests) => $requests->total() === 7 && $requests->count() === 5)
            ->assertViewHas('pendingRequests', 5)->assertViewHas('completedRequests', 2);

        $this->get(route('resident.document-requests.index', ['page' => 2]))
            ->assertOk()->assertSee('PAGE-REQUEST-2')->assertSee('PAGE-REQUEST-1')
            ->assertDontSee('PAGE-REQUEST-3')->assertDontSee('PAGE-REQUEST-8')
            ->assertViewHas('pendingRequests', 5)->assertViewHas('completedRequests', 2)
            ->assertViewHas('documentRequests', fn ($requests) => $requests->total() === 7 && $requests->count() === 2);
    }

    public function test_resident_without_requests_sees_empty_state(): void
    {
        $resident = User::factory()->create(['barangay_id' => Barangay::firstOrFail()->id]);
        $this->actingAs($resident)->get(route('resident.document-requests.index'))
            ->assertOk()->assertSee('No requests yet')
            ->assertViewHas('documentRequests', fn ($requests) => $requests->total() === 0);
    }
}
