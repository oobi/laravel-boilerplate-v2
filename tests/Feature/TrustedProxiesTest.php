<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Which proxies' X-Forwarded-* headers are believed (config/trustedproxy.php,
 * GitHub #19): the visitor's address and scheme behind a trusted proxy, and
 * nobody's word for it otherwise.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_request-origin', fn (Request $request): array => ['ip' => $request->ip(), 'secure' => $request->isSecure()]);
    }

    private function viaProxy(string $proxy): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $proxy])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/_request-origin'); // plain http, so only the header can make it secure
    }

    public function test_a_trusted_proxy_passes_on_the_visitors_address_and_scheme(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->viaProxy('10.0.0.5')->assertExactJson(['ip' => '203.0.113.9', 'secure' => true]);
    }

    public function test_with_nothing_configured_forwarded_headers_are_ignored(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->viaProxy('10.0.0.5')->assertExactJson(['ip' => '10.0.0.5', 'secure' => false]);
    }

    public function test_a_list_trusts_only_the_proxies_on_it(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8, 192.0.2.1']);

        $this->viaProxy('10.0.0.5')->assertJsonPath('ip', '203.0.113.9');
        $this->viaProxy('198.51.100.7')->assertExactJson(['ip' => '198.51.100.7', 'secure' => false]);
    }

    public function test_the_trusted_proxies_env_setting_is_what_the_framework_reads(): void
    {
        $this->assertSame('10.0.0.0/8', $this->configuredWith('10.0.0.0/8'));
    }

    /** Blank must stay null: the framework only auto-trusts Cloud, Forge and Vapor when it is. */
    public function test_a_blank_setting_keeps_the_platform_auto_trust(): void
    {
        $this->assertNull($this->configuredWith(''));
    }

    public function test_forwarded_headers_from_an_untrusted_caller_log_one_warning_an_hour(): void
    {
        config(['trustedproxy.proxies' => null]);
        Log::spy();

        $this->viaProxy('10.0.0.5');
        $this->viaProxy('10.0.0.5');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'TRUSTED_PROXIES'));
    }

    public function test_a_trusted_proxy_or_a_plain_request_logs_nothing(): void
    {
        Log::spy();

        // Plain first: headers set by viaProxy() stick for later requests in a test.
        config(['trustedproxy.proxies' => null]);
        $this->getJson('http://localhost/_request-origin');
        config(['trustedproxy.proxies' => '*']);
        $this->viaProxy('10.0.0.5');

        Log::shouldNotHaveReceived('warning');
    }

    /** The config file's value with TRUSTED_PROXIES set to $value, restoring any earlier setting. */
    private function configuredWith(string $value): ?string
    {
        $previous = $_SERVER['TRUSTED_PROXIES'] ?? null;
        $_SERVER['TRUSTED_PROXIES'] = $value;

        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            if ($previous === null) {
                unset($_SERVER['TRUSTED_PROXIES']);
            } else {
                $_SERVER['TRUSTED_PROXIES'] = $previous;
            }
        }
    }
}
