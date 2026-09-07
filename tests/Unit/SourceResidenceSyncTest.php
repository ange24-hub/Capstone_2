<?php

namespace Tests\Unit;

use App\Models\Inhabitant;
use App\Support\ResidenceWorkbookReader;
use App\Support\SourceResidenceSync;
use PHPUnit\Framework\TestCase;

class SourceResidenceSyncTest extends TestCase
{
    public function test_green_marks_elsewhere_without_changing_registry_status(): void
    {
        foreach (['FF00B050','FF92D050','FF00FF00','FFE2EFDA'] as $green) {
            $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, SourceResidenceSync::classify([$green], '')[0]);
        }
        $this->assertSame(Inhabitant::RESIDENCE_HERE, SourceResidenceSync::classify([''], 'STUDENT')[0]);
        $this->assertSame(Inhabitant::RESIDENCE_ELSEWHERE, SourceResidenceSync::classify([''], 'OFW')[0]);
        $this->assertSame(Inhabitant::RESIDENCE_UNCONFIRMED, SourceResidenceSync::classify(['FFFFFF00'], '')[0]);
        $this->assertFalse(SourceResidenceSync::isGreen('FFFF0000'));
    }

    public function test_excel_theme_and_indexed_green_are_resolved(): void
    {
        $themeGreen = ResidenceWorkbookReader::color(simplexml_load_string('<fgColor theme="6" tint="0.8"/>'), [6=>'70AD47']);
        $this->assertTrue(SourceResidenceSync::isGreen($themeGreen));
        $this->assertSame('FF00FF00', ResidenceWorkbookReader::color(simplexml_load_string('<fgColor indexed="3"/>'), []));
        $this->assertSame('FFFFFFFF', ResidenceWorkbookReader::color(simplexml_load_string('<fgColor theme="0"/>'), [0=>'FFFFFF']));
    }
}
