<?php

namespace App\Services;

use App\Models\Barangay;

class AssistantFollowUp
{
    private const METRICS = 'families|family|pamilya|households?|panimalay|balay|seniors?|senior citizens?|sc|pwd|male|female|lalaki|lalake|babaye|bata|children|adults?|age groups|edad';

    public function resolve(string $question, ?string $previous): string
    {
        $short = trim(preg_replace('/^(?:and|ug|unya|how about|what about)\s+/iu', '', trim($question)), " \t\n\r?!.");
        if (! $previous) return $question;
        // Only reuse the canonical topic/scope/date produced by context().
        if (! preg_match('/^Show (population(?: [a-z, ]+)?|migration) (Barangay [\pL .\x{2019}\x{0027}-]+|municipal)(?: ((?:19|20)\d{2}(?:-\d{2})?))?$/u', $previous, $context)) return $question;
        $scope = $context[2];
        if (preg_match('/^(?:(?:pila|how many)\s+)?(?:(?:pud|ka|ang|kabook|kabouk)\s+)*(?:'.self::METRICS.')(?:\s+(?:ug|and|or|o)\s+(?:'.self::METRICS.'))*$/iu', $short)) {
            return 'Show population '.$short.' '.$scope;
        }
        if (preg_match('/^(?:full report|full summary|population summary)$/iu', $short)) {
            return str_starts_with($context[1], 'migration') && mb_strtolower($short) !== 'population summary'
                ? 'Show migration '.$scope.(isset($context[3]) ? ' '.$context[3] : '')
                : 'Show population summary '.$scope;
        }
        if (preg_match('/^(?:download (?:the )?pdf|pdf|i-download ang pdf)$/iu', $short)) return $previous;
        $area = null;
        if (preg_match('/^(?:sa|in|barangay|brgy\.?)\s+(?:barangay\s+)?([\pL .\x{2019}\x{0027}-]+)$/iu', $short, $match)) $area = trim($match[1]);
        if ($area === null) $area = Barangay::pluck('name')->first(fn ($name) => mb_strtolower($name) === mb_strtolower($short));
        if ($area !== null) return 'Show '.$context[1].' Barangay '.$area.(isset($context[3]) ? ' '.$context[3] : '');
        if (preg_match('/^(?:municipal|whole municipality|all barangays)$/iu', $short)) return 'Show '.$context[1].' municipal'.(isset($context[3]) ? ' '.$context[3] : '');
        if (! preg_match('/\bmigration\b/iu', $previous)) return $question;
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december';
        if (! preg_match('/^(?:this month|last month|this year|last year|karong bulana|miaging bulan|niaging bulan|miaging tuig|(?:'.$months.')(?: (?:19|20)\d{2})?|(?:19|20)\d{2}(?:-\d{2})?)$/iu', $short)) return $question;
        preg_match('/\b((?:19|20)\d{2})\b/', $previous, $year);
        $base = trim(preg_replace('/\b(?:19|20)\d{2}(?:-\d{2})?\b/', '', $previous));
        // Month-name follow-ups keep the selected year. Relative dates use today.
        if (preg_match('/^(?:'.$months.')$/iu', $short) && isset($year[1])) $short .= ' '.$year[1];
        return $base.' '.$short;
    }

    public function context(array $answer): ?string
    {
        $facts = $answer['facts'] ?? null;
        if (! $facts) return null;
        $scope = str_starts_with($facts['scope'], 'Barangay ') ? $facts['scope'] : 'municipal';
        return isset($facts['recorded_events'])
            ? 'Show migration '.$scope.' '.($facts['month'] ?? $facts['year'])
            : 'Show population '.(isset($answer['focused_metrics']) ? implode(', ', $answer['focused_metrics']).' ' : '').$scope;
    }

    public function clarification(string $question, ?string $previous): ?array
    {
        if ($previous) return null;
        if (! preg_match('/^(?:(?:ug|and|unya|how about|what about)\s+|(?:last month|this month|last year|download pdf|pdf)\s*[?!.]*$)/iu', trim($question))) return null;
        return ['reply' => 'Please start with a complete question, such as “Show Looc migration this month” or “Pila ka pamilya sa Looc?”. Then you can ask a follow-up or request its PDF.',
            'scope' => 'RBIM system only', 'actions' => [], 'suggestions' => ['Show population summary', 'Show migration this month', 'What can I ask?']];
    }
}
