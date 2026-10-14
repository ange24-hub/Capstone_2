<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AssistantOllama
{
    private function endpoint(): ?string
    {
        $url = rtrim((string) config('assistant_ai.url'), '/');
        if (! preg_match('~^http://(?:127\.0\.0\.1|ollama):([0-9]{1,5})$~D', $url, $match)
            || (int) $match[1] < 1 || (int) $match[1] > 65535) {
            return null;
        }

        return $url;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(2)
            ->withOptions(['allow_redirects' => false, 'proxy' => '']);
    }

    /** Diagnostics never include user messages, credentials, or database records. */
    public function status(): string
    {
        if (! config('assistant_ai.enabled')) return 'disabled';
        $url = $this->endpoint();
        $model = (string) config('assistant_ai.model');
        if (! $url || ! preg_match('/^[a-zA-Z0-9_.:-]+$/D', $model) || str_contains(strtolower($model), 'cloud')) {
            return 'invalid_configuration';
        }

        try {
            $response = $this->http()->timeout(3)->get($url.'/api/tags');
            if (! $response->successful()) return 'unavailable';
            $models = $response->json('models');
            if (! is_array($models)) return 'unavailable';
            foreach ($models as $installed) {
                if (! is_array($installed) || ($installed['name'] ?? null) !== $model) continue;
                return empty($installed['remote_host']) && empty($installed['remote_model']) ? 'ready' : 'remote_model_rejected';
            }

            return 'model_missing';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    public function classify(string $question, array $schema, string $instructions): ?array
    {
        if (! config('assistant_ai.enabled')) return null;
        $key = 'assistant-ai:backoff:'.hash('sha256', config('assistant_ai.url').'|'.config('assistant_ai.model'));
        if (Cache::has($key)) return null;

        try {
            if ($this->status() !== 'ready') {
                Cache::put($key, true, 30);
                return null;
            }
            $response = $this->http()->timeout(max(3, min(25, (int) config('assistant_ai.timeout'))))
                ->post($this->endpoint().'/api/chat', [
                    'model' => config('assistant_ai.model'),
                    'stream' => false,
                    'keep_alive' => '5m',
                    'options' => ['temperature' => 0, 'num_ctx' => 4096, 'num_predict' => 220],
                    'format' => $schema,
                    'messages' => [
                        ['role' => 'system', 'content' => $instructions],
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);
            if (! $response->successful()) {
                Cache::put($key, true, 30);
                return null;
            }
            $content = $response->json('message.content');
            if (! is_string($content) || strlen($content) > 4000) return null;
            $result = json_decode($content, true, 16, JSON_THROW_ON_ERROR);

            return is_array($result) ? $result : null;
        } catch (\Throwable) {
            // A failed model must not take down chat or expose prompts in logs.
            Cache::put($key, true, 30);
            return null;
        }
    }
}
