<?php

namespace App\Services;

class RbiReportMonth
{
    public function resolve(?string $recordedMonth, ?string $legacyLabel = null): ?string
    {
        if ($recordedMonth !== null && preg_match('/^(?:19|20)\d{2}-(?:0[1-9]|1[0-2])$/', $recordedMonth)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $recordedMonth);
            if ($date && $date->format('Y-m') === $recordedMonth) {
                return $recordedMonth;
            }
        }
        $label = trim(preg_replace('/\s+/', ' ', $legacyLabel ?? ''));
        // A month name without its year must never acquire the current or birth year.
        if (! preg_match('/\b(?:19|20)\d{2}\b/', $label, $year)) {
            return null;
        }
        foreach (['!F j, Y', '!F j Y', '!M j, Y', '!F Y', '!M Y', '!Y-m-d', '!Y-m'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $label);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && $date->format('Y') === $year[0] && ($errors === false || (! $errors['warning_count'] && ! $errors['error_count']))) {
                return $date->format('Y-m');
            }
        }

        return null;
    }
}
