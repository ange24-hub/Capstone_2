<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class DockerDeploymentTest extends TestCase
{
    public function test_health_probe_checks_database_without_creating_a_session(): void
    {
        $response = $this->get('/up')->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertEmpty($response->headers->getCookies());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_health_probe_returns_503_without_exposing_database_errors(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('private-database-password'));

        $this->get('/up')->assertStatus(503)->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('private-database-password');
    }

    public function test_dokploy_proxy_preserves_https_urls_and_client_ip(): void
    {
        config(['app.trusted_proxies' => '*']);
        $this->registerProxyProbe();

        $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.2'])
            ->get('/_proxy-probe', $this->proxyHeaders())
            ->assertOk()->assertJson([
                'secure' => true,
                'ip' => '203.0.113.10',
                'login_url' => 'https://portal.example.test/login',
            ]);
    }

    public function test_explicit_proxy_ranges_are_supported(): void
    {
        config(['app.trusted_proxies' => '172.20.0.0/16, 10.0.0.2']);
        $this->registerProxyProbe();

        $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.2'])
            ->get('/_proxy-probe', $this->proxyHeaders())
            ->assertOk()->assertJson(['secure' => true, 'ip' => '203.0.113.10']);
    }

    public function test_unconfigured_proxies_cannot_spoof_https_or_client_ip(): void
    {
        config(['app.trusted_proxies' => null]);
        $this->registerProxyProbe();

        $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.2'])
            ->get('/_proxy-probe', $this->proxyHeaders())
            ->assertOk()->assertJson(['secure' => false, 'ip' => '172.20.0.2']);
    }

    private function registerProxyProbe(): void
    {
        Route::get('/_proxy-probe', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'login_url' => url('/login'),
        ]));
    }

    private function proxyHeaders(): array
    {
        return [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Host' => 'portal.example.test',
            'X-Forwarded-For' => '203.0.113.10',
        ];
    }
}
