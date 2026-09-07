<?php

namespace Tests\Unit;

use App\Models\Inhabitant;
use App\Support\SanIsidroWorkbook;
use PHPUnit\Framework\TestCase;

class SanIsidroWorkbookTest extends TestCase
{
    private function sheets(array $rows): array
    {
        return ['CONSOLIDATED RBI' => [3 => ['O' => 'SAN ISIDRO']] + $rows, 'DECEASED' => []];
    }

    private function person(string $name, string $number = ''): array
    {
        return ['A' => $number, 'C' => 'Example', 'D' => $name, 'G' => 'HEAD', 'J' => '30000'];
    }

    public function test_households_carry_forward_and_reviewed_rows_stay_unassigned(): void
    {
        $rows = [11 => $this->person('Head', '143'), 12 => $this->person('Child'),
            13 => $this->person('NextHead', '240')];
        foreach ([1347 => '143', 1348 => '143', 1349 => '144', 1350 => '145', 1351 => '145'] as $i => $hh) {
            $rows[$i] = $this->person('Pending'.$i, $hh);
        }
        $plan = SanIsidroWorkbook::plan($this->sheets($rows));
        $this->assertSame('143', $plan['active'][12]['B']);
        $this->assertArrayNotHasKey(239, $plan['households']);
        $this->assertCount(5, $plan['pending']);
        foreach (range(1347, 1351) as $i) {
            $this->assertSame('', $plan['active'][$i]['B']);
            $this->assertStringContainsString('Source household: '.$rows[$i]['A'], $plan['active'][$i]['Q']);
            $this->assertSame($rows[$i]['A'], $plan['pending'][$i]['row']['B']);
        }
    }

    public function test_deceased_row_one_is_reconciled_without_creating_a_second_person(): void
    {
        $sheets = $this->sheets([73 => $this->person('Historical', '15') + ['Q' => 'DECEASED']]);
        $sheets['DECEASED'][1] = ['A' => '15', 'B' => 'Example', 'C' => 'Historical', 'I' => '30000', 'Q' => 'JAN. 25, 2026'];
        $plan = SanIsidroWorkbook::plan($sheets);
        $this->assertCount(0, $plan['active']);
        $this->assertCount(1, $plan['deceased']);
        $this->assertSame('JAN. 25, 2026', $plan['deceased'][1]['Q']);
        $this->assertStringContainsString('CONSOLIDATED RBI row 73', $plan['deceased'][1]['P']);
        $this->assertSame([], $plan['new']);
    }

    public function test_overseas_cebu_and_explicit_transfer_rules(): void
    {
        foreach ([['P' => 'OFW'], ['Q' => 'ABROAD'], ['Q' => 'OUT OF COUNTRY']] as $row) {
            $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, SanIsidroWorkbook::residence($row)[0]);
        }
        $this->assertSame(Inhabitant::RESIDENCE_UNCONFIRMED, SanIsidroWorkbook::residence(['Q' => 'MITSUBISHI CEBU'])[0]);
        $rows = [];
        foreach (['TRANSFERRED TO BOGO', 'TRANSFERED', 'NOT TRANSFERRED', 'NO TRANSFERRED', 'NEVER TRANSFERRED', 'OUT OF COUNTRY'] as $i => $remark) {
            $rows[11 + $i] = $this->person('Person'.$i, '1') + ['Q' => $remark];
        }
        $plan = SanIsidroWorkbook::plan($this->sheets($rows));
        $this->assertCount(2, $plan['moved']);
        $this->assertCount(4, $plan['active']);
    }
}
