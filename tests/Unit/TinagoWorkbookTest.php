<?php

namespace Tests\Unit;

use App\Support\TinagoWorkbook;
use PHPUnit\Framework\TestCase;

class TinagoWorkbookTest extends TestCase
{
    private function sheets(array $rows): array
    {
        return ['Updated 2025' => [3 => ['O' => 'TINAGO']] + $rows, 'DECEASED' => [], 'NEW' => []];
    }

    public function test_updated_columns_household_inheritance_and_member_classification_are_preserved(): void
    {
        $plan = TinagoWorkbook::plan($this->sheets([
            10 => ['A'=>'1','B'=>'Example','C'=>'Head','F'=>'HEAD','G'=>'Purok Rose','H'=>'Tomas Oppus',
                'I'=>'30000','J'=>'40','K'=>'F','L'=>'MARRIED','M'=>'College','N'=>'In-School','O'=>'Catholic','P'=>'Teacher'],
            11 => ['B'=>'Example','C'=>'Child','F'=>'DAUGHTER','I'=>'40000','K'=>'F'],
            12 => ['A'=>'2','B'=>'Example','C'=>'Next','F'=>'HEAD'],
            13 => ['B'=>'Example','C'=>'Unknown','F'=>'HEAD'],
        ]));
        $this->assertSame('1', $plan['active'][11]['B']);
        $this->assertSame('2', $plan['active'][12]['B']);
        $this->assertSame('', $plan['active'][13]['B']);
        $this->assertSame('Example', $plan['active'][10]['C']);
        $this->assertSame('Head', $plan['active'][10]['D']);
        $this->assertSame('30000', $plan['active'][10]['J']);
        $this->assertSame('College', $plan['active'][10]['N']);
        $this->assertSame('Catholic', $plan['active'][10]['O']);
        $this->assertSame('Purok Rose', $plan['active'][10]['H']);
        $this->assertStringContainsString('In-School', $plan['active'][10]['Q']);
        $this->assertSame('Updated 2025 row 10', $plan['active'][10]['_source']);
    }

    public function test_deceased_people_are_not_duplicated_in_current_residents_and_conflicting_households_stay_unassigned(): void
    {
        $sheets = $this->sheets([
            10 => ['A'=>'1','B'=>'Example','C'=>'Dead','D'=>'Variant','I'=>'30000'],
            11 => ['A'=>'2','B'=>'Example','C'=>'Same','I'=>'31000'],
            12 => ['A'=>'3','B'=>'Example','C'=>'Same','I'=>'31000'],
            13 => ['A'=>'4','B'=>'VACANT'],
        ]);
        $sheets['DECEASED'][2] = ['A'=>'1','B'=>'Example','C'=>'Dead','D'=>'Source variant','I'=>'30000','Q'=>'JULY 9, 2020'];
        $plan = TinagoWorkbook::plan($sheets);
        $this->assertCount(1, $plan['deceased']);
        $this->assertCount(1, $plan['active']);
        $this->assertSame('JULY 9, 2020', $plan['deceased'][2]['Q']);
        $this->assertStringContainsString('Updated 2025 row 10', $plan['deceased'][2]['P']);
        $this->assertSame('', $plan['active'][11]['B']);
        $this->assertSame('unconfirmed', TinagoWorkbook::residence($plan['active'][11])[0]);
        $this->assertCount(2, $plan['pending']);
        $this->assertArrayHasKey(4, $plan['households']);
    }

    public function test_new_history_uses_printed_section_years_and_skips_repeated_headers(): void
    {
        $sheets = $this->sheets([]);
        $sheets['NEW'] = [
            1 => ['B'=>"NEW INHABITANT'S INFORMATIONS 2020"],
            2 => ['A'=>'1','B'=>'Example','C'=>'First','N'=>'JUNE'],
            27 => ['B'=>"NEW INHABITANT'S INFORMATIONS 2021"],
            28 => ['A'=>'2','B'=>'Example','C'=>'Second','N'=>'JANUARY'],
            29 => ['A'=>'3','B'=>'Example','C'=>'Third','N'=>'OCTOBER 2021'],
        ];
        $plan = TinagoWorkbook::plan($sheets);
        $this->assertCount(3, $plan['new']);
        $this->assertSame('JUNE', $plan['new'][2]['O']);
        $this->assertSame('2020-06-01', $plan['new'][2]['_reporting_month']);
        $this->assertSame('2021-01-01', $plan['new'][28]['_reporting_month']);
        $this->assertSame('2021-10-01', $plan['new'][29]['_reporting_month']);
        $this->assertStringContainsString('section heading', $plan['new'][2]['M']);
    }

    public function test_move_out_remains_registered_and_only_explicit_transfer_removes_from_consolidated(): void
    {
        $plan = TinagoWorkbook::plan($this->sheets([
            10 => ['A'=>'1','B'=>'Example','C'=>'Moved','Q'=>'MOVED OUT'],
            11 => ['B'=>'Example','C'=>'Transferred','Q'=>'TRANSFERRED TO CEBU'],
            12 => ['B'=>'Example','C'=>'Here','Q'=>'NOT TRANSFERRED'],
        ]));
        $this->assertCount(2, $plan['active']);
        $this->assertCount(1, $plan['moved']);
        $this->assertSame('living_elsewhere', TinagoWorkbook::residence($plan['active'][10])[0]);
        $this->assertFalse(TinagoWorkbook::transferred($plan['active'][12]));
    }

    public function test_tinago_red_names_go_to_deceased_without_inventing_a_death_date(): void
    {
        $plan = TinagoWorkbook::plan($this->sheets([
            10 => ['A'=>'1','B'=>'Example','C'=>'RedMarked','I'=>'30000','_fills'=>['B'=>'FFFF0000','C'=>'FFFF0000']],
            11 => ['B'=>'Example','C'=>'YellowMarked','_fills'=>['B'=>'FFFFFF00','C'=>'FFFFFF00']],
        ]));
        $this->assertCount(1, $plan['deceased']);
        $this->assertCount(1, $plan['active']);
        $this->assertSame('RedMarked', $plan['deceased'][10010]['C']);
        $this->assertStringContainsString('red source name highlight', $plan['deceased'][10010]['P']);
        $this->assertArrayNotHasKey('Q', $plan['deceased'][10010]);
    }

    public function test_current_outside_residence_is_distinct_from_birthplace_origin_and_local_residence(): void
    {
        foreach (['NOT LIVING IN TINAGO', 'NO LONGER RESIDING IN TINAGO', 'LIVING IN MAASIN',
            'RESIDING AT CEBU', 'WALA NA NAGPUYO SA TINAGO', 'DILI NA NAG PUYO SA TINAGO'] as $remark) {
            $this->assertSame('living_elsewhere', TinagoWorkbook::residence(['Q'=>$remark])[0], $remark);
            $this->assertFalse(TinagoWorkbook::transferred(['Q'=>$remark]));
        }
        foreach (['FROM BOGO', 'FROM CEBU', 'LIVING IN TINAGO', 'AT TINAGO'] as $remark) {
            $this->assertSame('living_here', TinagoWorkbook::residence(['Q'=>$remark])[0], $remark);
        }
        $this->assertSame('living_here', TinagoWorkbook::residence(['I'=>'CEBU','Q'=>''])[0]);
    }
}
