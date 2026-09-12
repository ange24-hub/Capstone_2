<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocalAiNarrator
{
    public function explain(array $facts): ?string
    {
        if (! config('local_ai.enabled')) return null;
        if ((array_key_exists('recorded_events', $facts) && $facts['recorded_events'] === 0)
            || (array_key_exists('residents', $facts) && $facts['residents'] === 0)) return null;
        $url = rtrim((string) config('local_ai.url'), '/');
        // Literal loopback only: do not allow external hosts, credentials or redirects.
        if (! preg_match('~^http://127\\.0\\.0\\.1:[0-9]{1,5}$~D', $url)) return null;
        $model = (string) config('local_ai.model');
        if (! preg_match('/^[a-zA-Z0-9_.:-]+$/D', $model) || str_contains(strtolower($model), 'cloud')) return null;

        $instructions = 'You write formal report observations. Return the report observation itself in JSON with an explanation field. Preserve the supplied comparison and technical terms. Use one sentence. Follow the example format.';
        // The small local model rewrites a server-verified observation; it never calculates totals.
        if (array_key_exists('in_migration', $facts)) {
            $observation = match (true) {
                $facts['in_migration'] > $facts['out_migration'] => 'Recorded in-migration events exceed recorded out-migration events.',
                $facts['in_migration'] < $facts['out_migration'] => 'Recorded out-migration events exceed recorded in-migration events.',
                default => 'Recorded in-migration and out-migration event counts are equal.',
            };
            $limitation = 'These are events, not unique people. Reporting completeness is unverified.';
        } else {
            $observation = match (true) {
                ($facts['female'] ?? 0) > ($facts['male'] ?? 0) => 'Female resident records outnumber male resident records.',
                ($facts['male'] ?? 0) > ($facts['female'] ?? 0) => 'Male resident records outnumber female resident records.',
                default => 'The report summarizes available resident records.',
            };
            $limitation = 'These are encoded records, not a complete census. Reporting completeness is unverified.';
        }
        $modelFacts = ['verified_observation' => $observation];
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => 'Recorded service requests exceed recorded approvals.'],
            ['role' => 'assistant', 'content' => '{"explanation":"There are more recorded service requests than recorded approvals."}'],
            ['role' => 'user', 'content' => $observation],
        ];
        if (!array_key_exists('in_migration', $facts)) {
            $messages = [
                ['role' => 'system', 'content' => 'Return JSON with one explanation: rewrite the supplied observation as one short sentence of at most 25 English words. Preserve its comparison and units. Do not add numbers, reasons, limitations or other claims. The application adds the limitations separately. Keep the term resident records. Describe records only.'],
                ['role' => 'user', 'content' => json_encode($modelFacts, JSON_THROW_ON_ERROR)],
            ];
        }

        try {
            $http = Http::acceptJson()->connectTimeout(2)
                ->timeout(max(3, min(25, (int) config('local_ai.timeout'))))
                ->withOptions(['allow_redirects' => false, 'proxy' => '']);
            $tags = $http->get($url.'/api/tags');
            if (! $tags->successful()) return null;
            $installed = collect($tags->json('models', []))->firstWhere('name', $model);
            if (! $installed || !empty($installed['remote_model']) || !empty($installed['remote_host'])) return null;
            $response = $http->post($url.'/api/chat', [
                'model' => $model, 'stream' => false, 'think' => false, 'keep_alive' => '1m',
                'options' => ['temperature' => 0, 'num_ctx' => 2048, 'num_predict' => 180],
                'format' => ['type' => 'object', 'properties' => ['explanation' => ['type' => 'string']], 'required' => ['explanation'], 'additionalProperties' => false],
                'messages' => $messages,
            ]);
            if (! $response->successful()) return null;
            $content = $response->json('message.content');
            if (! is_string($content) || strlen($content) > 5000) return null;
            $decoded = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
            $text = $decoded['explanation'] ?? null;
            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 1000 || preg_match('/[<>]/', $text)) return null;
            if (count(preg_split('/\s+/u', trim($text))) > 70) return null;
            if (array_key_exists('in_migration', $facts)
                && (preg_match('/\b(people|persons?|individuals?|residents?|population)\b/i', $text)
                    || !preg_match('/\bevents?\b/i', $text))) return null;
            if (preg_match('/\b(incomplete|complete|verified|unverified)\b/i', $text)) return null;
            if (!array_key_exists('in_migration', $facts) && !preg_match('/\bresident records\b/i', $text)) return null;
            // Reject invented quantities. The authoritative counts are displayed separately.
            preg_match_all('/\\d+(?:[.,]\\d+)*/', json_encode($modelFacts), $allowed);
            preg_match_all('/\\d+(?:[.,]\\d+)*/', $text, $used);
            $normalize = fn ($n) => str_replace(',', '', $n);
            if (array_diff(array_map($normalize, $used[0]), array_map($normalize, $allowed[0]))) return null;
            return trim($text).' '.$limitation;
        } catch (\Throwable $e) {
            // Keep prompts and aggregate data out of logs; return the verified database answer.
            return null;
        }
    }
}
