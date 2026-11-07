<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class RbiEntryClassification
{
    public static function labels(): array
    {
        return ['birth' => 'Birth / Newborn', 'in_migration' => 'In-migration', 'late_registration' => 'Late registration'];
    }

    public static function fields(): array
    {
        return ['entry_type', 'previous_residence', 'previous_residence_unknown', 'arrival_month', 'arrival_date'];
    }

    public static function issues(array $row, string $month): array
    {
        $type = $row['entry_type'] ?? '';
        if ($type === '') {
            return [];
        }
        if (! array_key_exists($type, self::labels())) {
            return ['entry_type' => 'Choose Birth / Newborn, In-migration or Late registration.'];
        }
        if ($type !== 'in_migration') {
            return [];
        }
        $errors = [];
        if (trim((string) ($row['previous_residence'] ?? '')) === '' && ! filter_var($row['previous_residence_unknown'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors['previous_residence'] = 'Enter the previous barangay / municipality or confirm that it was not recorded for this in-migrant.';
        }
        $value = (string) ($row['arrival_date'] ?? '');
        $arrivalMonth = trim((string) ($row['arrival_month'] ?? '')) ?: substr($value, 0, 7);
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $arrivalMonth) || $arrivalMonth !== $month) {
            $errors['arrival_month'] = 'Confirm the actual arrival month using the matching monthly RBI form.';
        } elseif ($arrivalMonth > now()->format('Y-m')) {
            $errors['arrival_month'] = 'The arrival month cannot be in the future.';
        } elseif (! empty($row['birth_date']) && strtotime($row['birth_date']) !== false && date('Y-m', strtotime($row['birth_date'])) > $arrivalMonth) {
            $errors['arrival_month'] = 'The arrival month cannot be before the person was born.';
        }
        // A historical monthly report need not invent an unknown exact arrival day.
        if ($value === '') {
            return $errors;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            $errors['arrival_date'] = 'Enter a valid actual arrival date.';
        } elseif (substr($value, 0, 7) !== $month) {
            $errors['arrival_date'] = 'The arrival date must belong to the reporting month. Use the monthly form for the actual arrival month.';
        } elseif ($value > now()->format('Y-m-d')) {
            $errors['arrival_date'] = 'The arrival date cannot be in the future.';
        } elseif (! empty($row['birth_date']) && strtotime($row['birth_date']) !== false && $date->getTimestamp() < strtotime($row['birth_date'])) {
            $errors['arrival_date'] = 'The arrival date cannot be before the person was born.';
        }

        return $errors;
    }

    public static function validateRows(array $rows, string $month): void
    {
        $errors = [];
        foreach ($rows as $index => $row) {
            foreach (self::issues($row, $month) as $field => $message) {
                $errors["rows.$index.$field"] = $message;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function isClassified(array $row, string $month): bool
    {
        return array_key_exists($row['entry_type'] ?? '', self::labels()) && self::issues($row, $month) === [];
    }

    public static function isArrival(array $row, string $month): bool
    {
        return ($row['entry_type'] ?? '') === 'in_migration' && self::isClassified($row, $month);
    }
}
