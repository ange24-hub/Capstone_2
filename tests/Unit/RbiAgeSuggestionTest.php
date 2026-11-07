<?php

namespace Tests\Unit;

use App\Services\RbiAgeSuggestion;
use App\Services\RbiReportMonth;
use PHPUnit\Framework\TestCase;

class RbiAgeSuggestionTest extends TestCase
{
    public function test_age_suggestions_are_relative_to_the_report_month_not_today(): void
    {
        $service = new RbiAgeSuggestion;
        $this->assertSame('birth', $service->forMonth('2021-05-11', '2021-05')['type']);
        $this->assertSame(0, $service->forMonth('2021-05-11', '2021-05')['age_months']);
        $this->assertSame('birth', $service->forMonth('2020-06-01', '2021-05')['type']);
        $this->assertSame('in_migration', $service->forMonth('2020-05-01', '2021-05')['type']);
        foreach ([null, '', 'invalid', '2021-02-30', '2021-06-01'] as $date) {
            $this->assertNull($service->forMonth($date, '2021-05')['type']);
        }
    }

    public function test_legacy_labels_require_an_explicit_valid_year(): void
    {
        $service = new RbiReportMonth;
        $this->assertSame('2024-09', $service->resolve(null, 'SEPTEMBER 10, 2024'));
        $this->assertSame('2020-06', $service->resolve('2020-06', 'September 2024'));
        $this->assertSame('2021-07', $service->resolve(null, 'JULY 2021'));
        $this->assertNull($service->resolve(null, 'JULY'));
        $this->assertNull($service->resolve(null, 'SEPTEMBER 31, 2024'));
        $this->assertNull($service->resolve('0021-07'));
    }
}
