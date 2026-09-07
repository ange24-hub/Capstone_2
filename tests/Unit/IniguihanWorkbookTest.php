<?php

namespace Tests\Unit;

use App\Support\IniguihanWorkbook;
use PHPUnit\Framework\TestCase;

class IniguihanWorkbookTest extends TestCase
{
    public function test_household_headers_assign_members_and_ignore_form_labels(): void
    {
        $member = ['A' => 'Example', 'B' => 'Resident', 'C' => 'Middle', 'H' => '30000', 'J' => 'F'];
        $sheets = ['Sheet1' => [
            3 => ['J' => 'D. BARANGAY: INIGUIHAN'],
            4 => ['J' => 'E. HOUSEHOLD NO. 1 - Purok Barag-Barag'],
            8 => ['A' => 'LAST', 'B' => 'FIRST'],
            11 => $member,
            12 => ['A' => 'Example', 'B' => 'Child'],
            40 => ['A' => 'Prepared by:', 'B' => 'Secretary'],
            50 => ['J' => 'E. HOUSEHOLD NO. 2 - Purok Agukoy'],
            57 => ['A' => 'Another', 'B' => 'Resident'],
        ], 'Sheet2' => [1 => $member + ['P' => 'February 27, 2026']]];

        $unconfirmed = IniguihanWorkbook::normalize($sheets);
        $this->assertCount(4, $unconfirmed['CONSOLIDATED RBI']); // Includes barangay metadata.
        $this->assertSame('1', $unconfirmed['CONSOLIDATED RBI'][12]['B']);
        $this->assertSame('Purok Agukoy', $unconfirmed['households'][2]['purok']);
        $this->assertSame([], $unconfirmed['DECEASED']); // Sheet2 is not imported.
        $sheets['Sheet1'][11]['_red'] = true;
        $confirmed = IniguihanWorkbook::normalize($sheets);
        $this->assertCount(2, $confirmed['households']);
        $this->assertArrayNotHasKey(11, $confirmed['CONSOLIDATED RBI']);
        $this->assertSame('1', $confirmed['DECEASED'][11]['A']);
        $this->assertSame('', $confirmed['DECEASED'][11]['P']);
        $this->assertArrayHasKey(12, $confirmed['CONSOLIDATED RBI']);
    }

    public function test_invalid_birth_dates_are_not_silently_corrected(): void
    {
        foreach (['07/301976', '01/091978', '02/29/1979', '02/24/2978'] as $value) {
            $this->assertNull(IniguihanWorkbook::date($value));
        }
        $this->assertSame('1976-07-30', IniguihanWorkbook::date('07/30/1976'));
        $this->assertSame('1982-02-18', IniguihanWorkbook::date('30000'));
    }
}
