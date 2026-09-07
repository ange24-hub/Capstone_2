<?php

namespace Tests\Unit;

use App\Support\SanMiguelWorkbook;
use PHPUnit\Framework\TestCase;

class SanMiguelWorkbookTest extends TestCase
{
    private function sheets(array $rows): array
    {
        return ['CONSOLIDATED RBI' => [3 => ['O' => 'SAN MIGUEL']] + $rows, 'DECEASED' => [], 'NEW' => []];
    }

    public function test_transfers_include_household_destinations_and_source_spellings(): void
    {
        foreach (['TRANSFERRED TO CEBU', 'TRANSFRRED TO BOGO', 'TRANSFEERED TO MASLOG', 'TRANSFFERES TO SOGOD',
            'ransfeered to Davao', 'TREANSFERRED ZAMBAONGA', 'TRANSFERRED TO HH126', 'TRANSFERRED'] as $remark) {
            $this->assertTrue(SanMiguelWorkbook::transferred(['Q' => $remark]), $remark);
        }
        foreach (['AT HH#123', 'HH 120', 'AT CEBU', 'NOT TRANSFERRED TO BOGO'] as $remark) {
            $this->assertFalse(SanMiguelWorkbook::transferred(['Q' => $remark]), $remark);
        }
        $plan = SanMiguelWorkbook::plan($this->sheets([
            11 => ['B' => '1', 'C' => 'Example', 'D' => 'Transferred', 'Q' => 'TRANSFERRED TO HH126'],
            12 => ['B' => '1', 'C' => 'Example', 'D' => 'Local', 'Q' => 'ATHH #126'],
            13 => ['B' => '126', 'C' => 'Example', 'D' => 'Head'],
        ]));
        $this->assertSame('1', $plan['moved'][11]['B']);
        $this->assertSame('126', $plan['active'][12]['B']);
        $this->assertSame('living_elsewhere', SanMiguelWorkbook::residence($plan['active'][12])[0]);
        foreach (['AT HH#123', 'HH#123', 'HH 120', 'ATHH #124'] as $remark) {
            $this->assertFalse(SanMiguelWorkbook::transferred(['Q' => $remark]));
            $this->assertSame('living_elsewhere', SanMiguelWorkbook::residence(['Q' => $remark])[0]);
        }
    }

    public function test_duplicate_identity_uses_explicit_household_and_conflicts_stay_unassigned(): void
    {
        $plan = SanMiguelWorkbook::plan($this->sheets([
            11 => ['B' => '28', 'C' => 'Example', 'D' => 'Local', 'J' => '30000', 'Q' => 'AT HH#123'],
            12 => ['B' => '28', 'C' => 'Example', 'D' => 'Away', 'J' => '31000', 'Q' => 'TRANSFERRED TO CEBU'],
            15 => ['B' => '123', 'C' => 'Example', 'D' => 'Local', 'J' => '30000'],
            16 => ['B' => '123', 'C' => 'Example', 'D' => 'Away', 'J' => '31000', 'Q' => 'TRANSFERRED TO CEBU'],
        ]));
        $this->assertCount(1, $plan['active']);
        $this->assertCount(1, $plan['moved']);
        $this->assertSame('123', $plan['active'][11]['B']);
        $this->assertSame('', $plan['moved'][12]['B']);
        $this->assertStringContainsString('Source households: 28 / 123', $plan['moved'][12]['Q']);
    }

    public function test_shifted_deceased_columns_and_new_months_are_preserved(): void
    {
        $sheets = $this->sheets([11 => ['B' => '1', 'C' => 'Example', 'D' => 'Dead', 'J' => '30000', 'Q' => 'DECAESED']]);
        $sheets['DECEASED'][1] = ['A' => 'NO.'];
        $sheets['DECEASED'][2] = ['A' => '1.', 'B' => '1', 'C' => 'Example', 'D' => 'Dead', 'J' => '30000', 'R' => 'JULY'];
        $sheets['NEW'][2] = ['A' => '1', 'B' => 'Example', 'C' => 'Child', 'N' => 'MAY'];
        $plan = SanMiguelWorkbook::plan($sheets);
        $this->assertCount(1, $plan['deceased']);
        $this->assertCount(0, $plan['active']);
        $this->assertSame('JULY', $plan['deceased'][2]['Q']);
        $this->assertSame('MAY', $plan['new'][2]['O']);
        $this->assertSame('unconfirmed', SanMiguelWorkbook::residence(['Q' => "NATURE'S SPRING CEBU"])[0]);
        $this->assertSame('unconfirmed', SanMiguelWorkbook::residence(['Q' => '', '_fills' => ['C' => 'FFFF0000']])[0]);
        $this->assertSame('living_elsewhere', SanMiguelWorkbook::residence(['P' => 'OFW', 'Q' => 'HH#123'])[0]);
    }
}
