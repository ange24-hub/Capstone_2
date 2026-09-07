<?php

namespace Tests\Unit;

use App\Models\Inhabitant;
use App\Support\PunongWorkbook;
use PHPUnit\Framework\TestCase;

class PunongWorkbookTest extends TestCase
{
    public function test_isolated_address_highlights_do_not_mean_living_elsewhere(): void
    {
        foreach (['FARMER', 'SELF-EMPLOYED'] as $remark) {
            $this->assertSame(Inhabitant::RESIDENCE_HERE, PunongWorkbook::residence([
                '_fills' => ['H' => 'FFFFFF00'], 'R' => $remark,
            ])[0]);
        }
    }

    public function test_yellow_names_or_location_remarks_mean_living_elsewhere(): void
    {
        $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, PunongWorkbook::residence([
            '_fills' => ['C' => 'FFFFFF00', 'D' => 'FFFFFF00'],
        ])[0]);
        foreach (['MANILA', 'OSY/HINUNANGAN', 'IN SCHOOL/LILO-AN', 'LNP/BONTOC', 'OUT OF THE BARANGAY', 'SOLO PARENT/OFW'] as $remark) {
            $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, PunongWorkbook::residence(['R' => $remark])[0]);
        }
        $this->assertSame(Inhabitant::RESIDENCE_HERE, PunongWorkbook::residence(['R' => 'IN SCHOOL'])[0]);
    }
}
