<?php

namespace App\Services;

class AssistantPopulationAnswer
{
    /** Reuse explicit user filters when interpreting model output. */
    public function requestedMetrics(string $question): array
    {
        if (preg_match('/\b(summary|summarize|overview|report|coverage|encoded|walay data|naay data)\b/iu', $question)) return [];
        $patterns = [
            'families' => 'family|families|pamilya', 'households' => 'households?|panimalay|balay',
            'male' => 'males?|lalaki|lalake', 'female' => 'females?|babaye',
            'seniors' => 'seniors?|senior citizens?|tigulang|sc', 'pwd' => 'pwd',
            'children' => 'children|bata', 'adults' => 'adults?', 'ages' => 'age groups?|ages|edad',
        ];
        $selected = array_keys(array_filter($patterns, fn ($pattern) => preg_match('/\b('.$pattern.')\b/iu', $question)));
        if (! $selected && preg_match('/\b(pila|how many|count|total)\b/iu', $question)) $selected = ['residents'];
        return $selected;
    }

    /** Present selected metrics from the same snapshot used by the formal report. */
    public function focus(string $question, array $report): ?array
    {
        $selected = $this->requestedMetrics($question);
        if (! $selected) return null;
        $values = [
            'residents' => [AssistantLanguage::text('Registered residents'), $report['totalRecords']],
            'families' => [AssistantLanguage::text('Identified families'), $report['totalFamilies']],
            'households' => [AssistantLanguage::text('Households'), $report['totalHouseholds']],
            'male' => [AssistantLanguage::text('Male'), $report['sex']['Male']], 'female' => [AssistantLanguage::text('Female'), $report['sex']['Female']],
            'seniors' => [AssistantLanguage::text('Senior citizens'), $report['totalSeniors']], 'pwd' => [AssistantLanguage::text('PWD markers'), $report['totalPwd']],
            'children' => [AssistantLanguage::text('Children aged 0-17'), $report['ages']['0–4'] + $report['ages']['5–17']],
            'adults' => [AssistantLanguage::text('Adults aged 18+'), $report['ages']['18–59'] + $report['ages']['60+']],
        ];
        $lines = [$report['scopeLabel'].AssistantLanguage::text(' — as of ').$report['generatedAt']->format('M d, Y')];
        foreach ($selected as $metric) {
            if ($metric === 'ages') {
                foreach ($report['ages'] as $label => $count) $lines[] = AssistantLanguage::text($label).': '.number_format($count);
            } else {
                [$label, $count] = $values[$metric];
                $lines[] = $label.': '.number_format($count);
            }
        }
        if (in_array('families', $selected)) {
            $lines[] = AssistantLanguage::text('Separate family numbers count as separate families. Single-person households also count as families.');
            if ($report['recordsWithoutFamily']) $lines[] = number_format($report['recordsWithoutFamily']).AssistantLanguage::text(' records have no family number; the identified family count may be incomplete.');
        }
        if (in_array('households', $selected)) $lines[] = AssistantLanguage::text('Decimal household numbers share their base household; families may have separate numbers within it.');
        if (in_array('seniors', $selected)) $lines[] = AssistantLanguage::text('Based on birth dates (60+) or recognized SC remarks, without double-counting a record.');
        if (in_array('pwd', $selected)) $lines[] = AssistantLanguage::text('Based only on recognized PWD remarks. Missing remarks do not confirm absence of disability.');
        if (array_intersect(['children', 'adults', 'ages'], $selected)) $lines[] = AssistantLanguage::text('Ages use valid birth dates as of today. Unknown / invalid birth dates: ').number_format($report['ages']['Unknown / invalid birth date']).'.';
        if (array_intersect(['male', 'female'], $selected)) $lines[] = AssistantLanguage::text('Sex not specified / other: ').number_format($report['sex']['Not specified / other']).'.';
        $lines[] = AssistantLanguage::text('Based on available records across all statuses; completeness unverified.');
        if (! $report['totalRecords']) $lines[] = AssistantLanguage::text('No resident records encoded; this does not mean zero population.');
        return ['reply' => implode("\n", $lines), 'metrics' => $selected];
    }
}
