<?php

namespace Tests\Unit;

use App\Services\LocalConcernClassifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalConcernClassifierTest extends TestCase
{
    public function test_external_urls_and_cloud_models_are_never_contacted(): void
    {
        Http::fake();
        config(['local_ai.enabled' => true, 'local_ai.model' => 'test']);
        foreach (['https://example.com', 'http://127.0.0.1:11434@evil.test', 'http://localhost:11434'] as $url) {
            config(['local_ai.url' => $url]);
            $this->assertNull(app(LocalConcernClassifier::class)->suggest('Title', 'Description'));
        }
        config(['local_ai.url' => 'http://127.0.0.1:11434', 'local_ai.model' => 'test-cloud']);
        $this->assertNull(app(LocalConcernClassifier::class)->suggest('Title', 'Description'));
        Http::assertNothingSent();
    }

    public function test_remote_model_metadata_and_unavailable_server_leave_classification_manual(): void
    {
        config(['local_ai.enabled' => true, 'local_ai.url' => 'http://127.0.0.1:11434', 'local_ai.model' => 'test']);
        Http::fake(['*/api/tags' => Http::sequence()->push(['models' => [['name' => 'test', 'remote_host' => 'example.com']]])->push([], 503)]);
        $this->assertNull(app(LocalConcernClassifier::class)->suggest('Title', 'Description'));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/chat'));
        $this->assertNull(app(LocalConcernClassifier::class)->suggest('Title', 'Description'));
    }
}
