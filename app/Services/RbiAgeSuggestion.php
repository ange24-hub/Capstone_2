<?php

namespace App\Services;

class RbiAgeSuggestion
{
    public function forMonth(?string $birthDate, ?string $month): array
    {
        $unknown = ['age_months' => null, 'type' => null, 'label' => 'Age unavailable — review'];
        $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate ?? '');
        $report = \DateTimeImmutable::createFromFormat('!Y-m', $month ?? '');
        if (! $birth || $birth->format('Y-m-d') !== $birthDate || ! $report || $report->format('Y-m') !== $month) {
            return $unknown;
        }
        $end = $report->modify('last day of this month');
        if ($birth > $end) {
            return $unknown;
        }
        $age = $birth->diff($end);
        $months = $age->y * 12 + $age->m;

        return ['age_months' => $months, 'type' => $months < 12 ? 'birth' : 'in_migration',
            'label' => $months < 12 ? 'Probable newborn — age only' : 'Probable in-migrant — age only'];
    }
}
