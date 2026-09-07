<?php

namespace Tests\Unit;

use App\Support\LuanWorkbook;
use PHPUnit\Framework\TestCase;

class LuanWorkbookTest extends TestCase
{
    public function test_luan_maps_death_dates_and_keeps_place_only_remarks_active(): void
    {
        $sheets = ['CONSOLIDATED RBI' => [
            3 => ['O'=>'LUAN'],
            11 => ['B'=>'1','C'=>'Example','D'=>'Resident','Q'=>'MANILA'],
            12 => ['B'=>'1','C'=>'Example','D'=>'Overseas','Q'=>'SAUDI'],
            13 => ['B'=>'2','C'=>'Example','D'=>'Departed','Q'=>'TRANSFERRED TO CEBU'],
            14 => ['B'=>'3','C'=>'Example','D'=>''],
        ], 'DECEASED' => [2 => ['A'=>'','B'=>'Example','C'=>'Historical','P'=>'MAY 9, 2026']], 'NEW'=>[]];
        $plan = LuanWorkbook::plan($sheets);
        $this->assertCount(3, $plan['households']);
        $this->assertCount(2, $plan['active']);
        $this->assertCount(1, $plan['moved']);
        $this->assertCount(1, $plan['pending']);
        $this->assertSame('Not recorded', $plan['deceased'][2]['A']);
        $this->assertSame('MAY 9, 2026', $plan['deceased'][2]['Q']);
        $this->assertSame('', $plan['deceased'][2]['P']);
        $this->assertSame([], $plan['new']);
    }
}
