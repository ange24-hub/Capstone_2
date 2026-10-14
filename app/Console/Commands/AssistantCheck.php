<?php

namespace App\Console\Commands;

use App\Services\AssistantIntentInterpreter;
use App\Services\AssistantOllama;
use Illuminate\Console\Command;

class AssistantCheck extends Command
{
    protected $signature = 'assistant:check {--probe : Test a synthetic question against the installed model}';

    protected $description = 'Check chatbot Ollama connectivity and model availability without reading resident data';

    public function handle(AssistantOllama $ollama, AssistantIntentInterpreter $interpreter): int
    {
        $status = $ollama->status();
        if ($status !== 'ready') {
            $this->error(match ($status) {
                'disabled' => 'Set ASSISTANT_AI_ENABLED=true and restart/redeploy the app.',
                'invalid_configuration' => 'Use a local model and http://ollama:11434 (Docker) or http://127.0.0.1:11434 (local).',
                'model_missing' => 'Ollama is reachable, but ASSISTANT_AI_MODEL is not installed. Pull that exact model in the ollama container.',
                'remote_model_rejected' => 'The configured model is cloud-backed. Install a local model.',
                default => 'Cannot reach Ollama. Check the ai Compose profile, ollama container, and shared ai network.',
            });
            return self::FAILURE;
        }
        $this->info('Ollama is reachable and the configured local model is installed.');
        if ($this->option('probe')) {
            $plan = $interpreter->classify('How many people live in our barangay?');
            if (($plan['intent'] ?? null) !== 'population') {
                $this->error('The model did not classify the test question correctly. Check its logs, resources, and timeout.');
                return self::FAILURE;
            }
            $this->info('The model correctly understood the synthetic population question.');
        }

        return self::SUCCESS;
    }
}
