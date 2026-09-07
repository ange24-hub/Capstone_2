<?php

namespace Tests\Unit;

use App\Support\MagataWorkbook;
use PHPUnit\Framework\TestCase;

class MagataWorkbookTest extends TestCase
{
    public function test_magata_start_row_colors_and_deceased_columns_are_mapped(): void
    {
        $sheets = ['CONSOLIDATED RBI'=>[
            8=>['J'=>'9'],
            9=>['B'=>'1','C'=>'Example','D'=>'Resident','H'=>'P-SILANGAN,MAG-ATA,TOSL','Q'=>'OFW','_fill'=>'FFFFFF00'],
            10=>['B'=>'1','C'=>'Example','D'=>'Departed','Q'=>'TRANSFER MASLOG'],
            11=>['B'=>'2','C'=>'Example','D'=>'Historical','E'=>'Middle','J'=>'30001','_fill'=>'FFFF0000'],
            12=>['B'=>'2','C'=>'Another','D'=>'Historical','_fill'=>'FFFF0000'],
        ],'DECEASE INFO'=>[
            5=>['B'=>'2','C'=>'Example','D'=>'Historical','E'=>'Middle','J'=>'30000','Q'=>'DECEASED','R'=>'MAY 9, 2026'],
        ]];
        $plan = MagataWorkbook::plan($sheets);
        $this->assertCount(2, $plan['households']);
        $this->assertArrayHasKey(9, $plan['active']);
        $this->assertCount(1, $plan['active']);
        $this->assertCount(1, $plan['moved']);
        $this->assertCount(2, $plan['deceased']);
        $this->assertSame('MAY 9, 2026', $plan['deceased'][5]['Q']);
        $this->assertSame('30000', $plan['deceased'][5]['I']);
        $this->assertStringContainsString('Consolidated birth date: 30001', $plan['deceased'][5]['P']);
    }

    public function test_unconfirmed_similar_names_are_held_for_review(): void
    {
        $sheets = ['CONSOLIDATED RBI'=>[8=>['J'=>'9'],9=>['B'=>'92','C'=>'VECINA','D'=>'VICENTE','E'=>'PALERO','H'=>'MAG-ATA','_fill'=>'FFFF0000']],
            'DECEASE INFO'=>[12=>['B'=>'','C'=>'VECINA','D'=>'VINCENT','E'=>'PALERO']]];
        $review = MagataWorkbook::plan($sheets);
        $this->assertCount(1, $review['pending']);
        $this->assertCount(1, $review['deceased']);
        $merged = MagataWorkbook::plan($sheets, 'merge');
        $this->assertCount(0, $merged['pending']);
        $this->assertCount(1, $merged['deceased']);
        $this->assertSame('92', $merged['deceased'][12]['A']);
        $this->assertCount(2, MagataWorkbook::plan($sheets, 'distinct')['deceased']);
    }
}
