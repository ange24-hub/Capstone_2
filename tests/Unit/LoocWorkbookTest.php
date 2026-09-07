<?php

namespace Tests\Unit;

use App\Support\LoocWorkbook;
use PHPUnit\Framework\TestCase;

class LoocWorkbookTest extends TestCase
{
    public function test_deceased_are_deduplicated_and_unassigned_people_are_not_given_a_household(): void
    {
        $sheets = ['CONSOLIDATED RBI' => [
            3 => ['P'=>'LOOC'],
            11 => ['B'=>'1','C'=>'Example','D'=>'Active','H'=>'Purok 1'],
            12 => ['B'=>'','C'=>'Family','D'=>'Departed','Q'=>'TRANSFERRED TO MANILA'],
            13 => ['B'=>'','C'=>'Family','D'=>'Away','Q'=>'AT MANILA','_fill'=>'FFFFFF00'],
            14 => ['B'=>'3','C'=>'TORION','D'=>'Example','E'=>'Middle','J'=>'30000','Q'=>'DECEASED','_fill'=>'FFFFFF00'],
            15 => ['B'=>'','C'=>'Other','D'=>'Unassigned'],
            16 => ['B'=>'4','C'=>'Partial','D'=>''],
            17 => ['B'=>'','C'=>'Family','D'=>'Unconfirmed','Q'=>'TRANSFERRED','_fill'=>'FFFFFF00'],
        ], 'DECEASED' => [2 => ['A'=>'3','B'=>'1.TORION','C'=>'Example','D'=>'Middle','I'=>'30000','Q'=>'MAY 25, 2021']],
            'NEW' => [2 => ['A'=>'1','B'=>'Example','C'=>'New','O'=>'MAY 2020']]];
        $plan = LoocWorkbook::plan($sheets);
        $this->assertCount(3, $plan['households']);
        $this->assertCount(4, $plan['active']);
        $this->assertCount(1, $plan['moved']);
        $this->assertCount(1, $plan['deceased']);
        $this->assertSame('MAY 25, 2021', $plan['deceased'][2]['Q']);
        $this->assertSame('', $plan['moved'][12]['B']);
        $this->assertCount(1, $plan['pending']);
        $this->assertSame('MAY 2020', $plan['new'][2]['O']);

        $this->assertArrayHasKey(13, $plan['active']);
        $this->assertArrayHasKey(17, $plan['active']);
        $this->assertSame('', $plan['active'][15]['B']);
    }
}
