<?php

namespace Tests\Unit;

use App\Models\Inhabitant;
use App\Support\SanAntonioWorkbook;
use PHPUnit\Framework\TestCase;

class SanAntonioWorkbookTest extends TestCase
{
    private function sheets(): array
    {
        return ['CONSOLIDATED RBI' => [3 => ['O' => 'SAN ANTONIO']], 'DECEASED' => [], 'NEW' => [], 'OUT' => []];
    }

    public function test_out_requires_explicit_transfer_to_be_moved_out(): void
    {
        $sheets = $this->sheets();
        foreach (['', 'TRANSFERRED TO BOGO', 'TRANSFERED TO BOGO', 'NOT TRANSFERRED', 'MOVE TO SOGOD'] as $i => $remark) {
            $sheets['OUT'][$i + 3] = ['A' => '1', 'B' => 'Example', 'C' => 'Person'.$i, 'P' => $remark];
        }
        $plan = SanAntonioWorkbook::plan($sheets);
        $this->assertCount(3, $plan['active']);
        $this->assertCount(2, $plan['moved']);
        foreach ($plan['active'] as $row) {
            $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, SanAntonioWorkbook::residence($row)[0]);
        }
    }

    public function test_out_duplicates_are_merged_and_consolidated_match_stays_elsewhere(): void
    {
        $sheets = $this->sheets();
        $sheets['CONSOLIDATED RBI'][11] = ['B' => '1', 'C' => 'Example', 'D' => 'Person', 'J' => '30000'];
        $sheets['OUT'][3] = $sheets['OUT'][4] = ['A' => '1', 'B' => 'Example', 'C' => 'Person', 'I' => '30000'];
        $plan = SanAntonioWorkbook::plan($sheets);
        $this->assertCount(1, $plan['active']);
        $this->assertCount(0, $plan['moved']);
        $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, SanAntonioWorkbook::residence($plan['active'][11])[0]);
        $this->assertStringContainsString('OUT row 4', $plan['active'][11]['Q']);
    }

    public function test_deceased_duplicates_preserve_death_date_and_are_excluded_from_active(): void
    {
        $sheets = $this->sheets();
        $sheets['DECEASED'][2] = ['A' => '1', 'B' => 'Example', 'C' => 'Person', 'I' => '30000', 'P' => 'APRIL 1, 2026'];
        $sheets['DECEASED'][3] = ['A' => '1', 'B' => 'Example', 'C' => 'Person', 'I' => '30000'];
        $sheets['CONSOLIDATED RBI'][11] = ['B' => '1', 'C' => 'Example', 'D' => 'Person', 'J' => '30000'];
        $plan = SanAntonioWorkbook::plan($sheets);
        $this->assertCount(1, $plan['deceased']);
        $this->assertCount(0, $plan['active']);
        $this->assertSame('APRIL 1, 2026', $plan['deceased'][2]['Q']);
    }
}
