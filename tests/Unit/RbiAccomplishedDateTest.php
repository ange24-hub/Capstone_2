<?php

namespace Tests\Unit;

use App\Models\BarangayRbiUpdate;
use Tests\TestCase;

class RbiAccomplishedDateTest extends TestCase
{
    public function test_explicit_completion_date_takes_precedence_over_submission_and_creation(): void
    {
        $report = new BarangayRbiUpdate(['as_of_date' => '2026-09-25', 'submitted_at' => '2026-09-30 18:30:00']);
        $report->created_at = '2026-09-01 00:00:00';
        $this->assertSame('09/25/2026', $report->accomplishedDate()->format('m/d/Y'));
    }

    public function test_legacy_submission_date_uses_philippine_time_and_preserves_the_stored_timestamp(): void
    {
        $report = new BarangayRbiUpdate(['submitted_at' => '2026-09-30 18:30:00']);
        $report->created_at = '2026-09-01 00:00:00';
        $stored = $report->submitted_at->toIso8601String();
        $this->assertSame('10/01/2026', $report->accomplishedDate()->format('m/d/Y'));
        $this->assertSame($stored, $report->submitted_at->toIso8601String());
    }

    public function test_draft_uses_its_original_creation_date(): void
    {
        $report = new BarangayRbiUpdate;
        $report->created_at = '2026-09-10 18:30:00';
        $this->assertSame('09/11/2026', $report->accomplishedDate()->format('m/d/Y'));
    }
}
