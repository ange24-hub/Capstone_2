<?php

namespace Tests\Unit;

use App\Rules\RecordedAge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecordedAgeTest extends TestCase
{
    public static function ages(): array
    {
        return [
            [0, true], ['25', true], ['150', true],
            ['2 months old', true], ['1 month old', true],
            ['3 days old', true], ['2 weeks', true], ['1 year old', true],
            ['2 MONTHS OLD', true], ['0 months old', true],
            ['-1', false], ['151', false], ['1801 months old', false],
            ['unknown', false], ['2 months old extra', false],
            [['2 months old'], false], [true, false],
        ];
    }

    #[DataProvider('ages')]
    public function test_recorded_age_accepts_age_units_and_rejects_invalid_values(mixed $age, bool $valid): void
    {
        $errors = [];
        (new RecordedAge)->validate('recorded_age', $age, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->assertSame($valid, $errors === []);
    }
}
