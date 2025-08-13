<?php

namespace Apogee\Watcher\Tests\Feature;

use Apogee\Watcher\Services\PSIClientService;
use Orchestra\Testbench\TestCase;
use Apogee\Watcher\WatcherServiceProvider;

class WatcherTestApiKeyCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        /** @SuppressWarnings("UnusedFormalParameter") */
        return [WatcherServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('watcher.psi_api_key', 'test_key');
        $app['config']->set('app.url', 'https://example.com');
    }

    public function test_command_reports_ok_and_score(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 200,
                    'score' => 91,
                    'error' => null,
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 200')
            ->expectsOutputToContain('Performance Score: 91')
            ->assertExitCode(0);
    }

    public function test_command_with_desktop_strategy(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 200,
                    'score' => 75,
                    'error' => null,
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key', ['--strategy' => 'desktop'])
            ->expectsOutputToContain('Testing PSI connectivity for https://example.com (desktop)')
            ->expectsOutputToContain('HTTP Code: 200')
            ->expectsOutputToContain('Performance Score: 75')
            ->assertExitCode(0);
    }

    public function test_command_without_api_key(): void
    {
        $this->app['config']->set('watcher.psi_api_key', null);

        $fake = new class(new \GuzzleHttp\Client(), null) extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 401,
                    'score' => null,
                    'error' => 'PSI API key is not configured',
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 401')
            ->expectsOutputToContain('Error: PSI API key is not configured')
            ->assertExitCode(1);
    }

    public function test_command_with_poor_performance(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 200,
                    'score' => 45,
                    'error' => null,
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 200')
            ->expectsOutputToContain('Performance Score: 45')
            ->assertExitCode(0);
    }

    public function test_command_with_api_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 400,
                    'score' => null,
                    'error' => 'Bad request: Invalid URL',
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 400')
            ->expectsOutputToContain('Error: Bad request: Invalid URL')
            ->assertExitCode(1);
    }

    public function test_command_without_app_url(): void
    {
        // Clear the app.url configuration completely
        $this->app['config']->set('app.url', '');
        
        // Also clear any environment variable that might be set
        putenv('APP_URL');

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('APP_URL is not set')
            ->assertExitCode(1);
    }

    public function test_command_with_invalid_strategy(): void
    {
        $this->artisan('watcher:test-api-key', ['--strategy' => 'invalid'])
            ->expectsOutputToContain('Invalid strategy')
            ->assertExitCode(1);
    }

    public function test_command_with_rate_limit_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 429,
                    'score' => null,
                    'error' => 'quota exceeded or rate limited (429)',
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 429')
            ->expectsOutputToContain('Error: quota exceeded or rate limited (429)')
            ->assertExitCode(1);
    }

    public function test_command_with_server_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                return [
                    'http_code' => 502,
                    'score' => null,
                    'error' => 'PSI server error (5xx) — retry later',
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('HTTP Code: 502')
            ->expectsOutputToContain('Error: PSI server error (5xx) — retry later')
            ->assertExitCode(1);
    }
}