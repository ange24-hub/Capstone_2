<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RecordedAge implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value))
            || strlen((string) $value) > 30
            || ! preg_match('/\A(\d{1,5})(?:\s+(days?|weeks?|months?|years?)(?:\s+old)?)?\z/i', trim((string) $value), $matches)) {
            $fail('Enter an age such as 25, 2 months old, or 1 year old.');

            return;
        }

        $years = (int) $matches[1] / match (strtolower($matches[2] ?? 'years')) {
            'day', 'days' => 365.25,
            'week', 'weeks' => 52.18,
            'month', 'months' => 12,
            default => 1,
        };

        if ($years > 150) {
            $fail('The age must not exceed 150 years.');
        }
    }
}
