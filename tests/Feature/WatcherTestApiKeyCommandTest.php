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
            public function runTest(string $url, string $strategy = 'mobile'): array {
                return [
                    'lighthouseResult' => [
                        'categories' => [
                            'performance' => ['score' => 0.91],
                        ],
                        'audits' => [
                            'largest-contentful-paint' => ['numericValue' => 1800],
                            'interaction-to-next-paint' => ['numericValue' => 120],
                            'cumulative-layout-shift' => ['numericValue' => 0.03],
                        ],
                    ],
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('OK: PSI API reachable.')
            ->expectsOutputToContain('Score: 91%')
            ->expectsOutputToContain('Performance: Excellent')
            ->assertExitCode(0);
    }

    public function test_command_with_desktop_strategy(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function runTest(string $url, string $strategy = 'mobile'): array {
                return [
                    'lighthouseResult' => [
                        'categories' => [
                            'performance' => ['score' => 0.75],
                        ],
                        'audits' => [
                            'largest-contentful-paint' => ['numericValue' => 2200],
                            'interaction-to-next-paint' => ['numericValue' => 150],
                            'cumulative-layout-shift' => ['numericValue' => 0.05],
                        ],
                    ],
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key', ['--strategy' => 'desktop'])
            ->expectsOutputToContain('Testing PSI connectivity for https://example.com (desktop)')
            ->expectsOutputToContain('Score: 75%')
            ->expectsOutputToContain('Performance: Good')
            ->assertExitCode(0);
    }

    public function test_command_with_poor_performance(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function runTest(string $url, string $strategy = 'mobile'): array {
                return [
                    'lighthouseResult' => [
                        'categories' => [
                            'performance' => ['score' => 0.45],
                        ],
                        'audits' => [
                            'largest-contentful-paint' => ['numericValue' => 3500],
                            'interaction-to-next-paint' => ['numericValue' => 300],
                            'cumulative-layout-shift' => ['numericValue' => 0.15],
                        ],
                    ],
                ];
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('Score: 45%')
            ->expectsOutputToContain('Performance: Needs improvement')
            ->assertExitCode(0);
    }

    public function test_command_without_api_key(): void
    {
        $this->app['config']->set('watcher.psi_api_key', null);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('PSI_API_KEY is not set')
            ->assertExitCode(1);
    }

    public function test_command_without_app_url(): void
    {
        $this->app['config']->set('app.url', null);

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

    public function test_command_with_api_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { parent::__construct($client, $key); }
            /** @SuppressWarnings("UnusedFormalParameter") */
            public function runTest(string $url, string $strategy = 'mobile'): array {
                throw new \RuntimeException('API key error: Invalid API key');
            }
        };

        $this->app->instance(PSIClientService::class, $fake);

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('Error connecting to PSI API')
            ->expectsOutputToContain('Please check your PSI_API_KEY configuration')
            ->assertExitCode(1);
    }
}