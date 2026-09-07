<?php
namespace Tests\Unit;
use App\Models\Inhabitant;
use App\Support\MaslogWorkbook;
use PHPUnit\Framework\TestCase;
class MaslogWorkbookTest extends TestCase
{
    public function test_deceased_markings_in_any_record_note_are_separate_and_places_remain_registered(): void
    {
        $rows = [3=>['O'=>'MASLOG'],10=>['B'=>'HH']];
        $base = ['B'=>'1','C'=>'Example','D'=>'Person','H'=>'PUROK 1'];
        $rows[11] = $base + ['Q'=>'CEBU CITY'];
        $rows[12] = $base + ['Q'=>'SC/PWD/DECEASED'];
        $rows[13] = $base + ['P'=>'DECEASED'];
        $rows[14] = $base + ['R'=>'DECEASED'];
        $rows[15] = ['C'=>'BLUE - DECEASED'];
        $plan = MaslogWorkbook::plan(['CONSOLIDATED RBI'=>$rows]);
        $this->assertCount(1,$plan['active']);
        $this->assertCount(3,$plan['deceased']);
        $this->assertCount(0,$plan['moved']);
        $this->assertCount(0,$plan['pending']);
        foreach (['CEBU CITY','SOGOD','now in Poland','STA. CRUZ','IN SAMAR','OFW'] as $remark) {
            $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE,MaslogWorkbook::residence(['FFFFFF00'],$remark)[0]);
        }
        $this->assertSame(Inhabitant::RESIDENCE_HERE,MaslogWorkbook::residence(['FFFFFF00'],'SC')[0]);
        $this->assertSame(Inhabitant::RESIDENCE_HERE,MaslogWorkbook::residence([''],'SLSU-BONTOC')[0]);
        $this->assertSame(Inhabitant::RESIDENCE_UNCONFIRMED,MaslogWorkbook::residence([''],'US CITIZEN')[0]);
    }
    public function test_exact_duplicate_is_counted_once_while_household_is_unconfirmed(): void
    {
        $person=['C'=>'ESPIRITU','D'=>'EMELIA','E'=>'PELAEZ','J'=>'18387','L'=>'F','Q'=>'SC'];
        $sheets=['CONSOLIDATED RBI'=>[3=>['O'=>'MASLOG'],10=>['B'=>'HH'],568=>$person+['B'=>'123'],993=>$person+['B'=>'229']]];
        $plan=MaslogWorkbook::plan($sheets);
        $this->assertCount(1,$plan['active']);
        $this->assertCount(2,$plan['households']);
        $this->assertSame('',$plan['active'][568]['B']);
        $this->assertCount(1,$plan['pending']);
        $confirmed=MaslogWorkbook::plan($sheets,'229');
        $this->assertSame('229',$confirmed['active'][568]['B']);
        $this->assertCount(0,$confirmed['pending']);
    }
}
