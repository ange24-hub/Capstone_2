<?php

namespace Tests\Unit;

use App\Support\RegistryRemarks;
use PHPUnit\Framework\TestCase;

class RegistryRemarksTest extends TestCase
{
    public function test_original_remarks_remain_and_generated_notes_are_hidden(): void
    {
        $this->assertSame('', RegistryRemarks::display('[Source: SAN ROQUE.xlsx row 11]'));
        $this->assertSame('Cebu', RegistryRemarks::display('Cebu [Source: SAN ROQUE.xlsx row 243]'));
        $this->assertSame('SC [verified]', RegistryRemarks::display('SC [verified] [Source age: unknown] [Also DECEASED row 2]'));
        $this->assertSame('', RegistryRemarks::display('[Household assignment pending confirmation; source address: San Roque] [Original household: 1] [Duplicate source: rows 1 and 2]'));
        $this->assertSame('AT HH#123', RegistryRemarks::display('AT HH#123'));
    }
}
