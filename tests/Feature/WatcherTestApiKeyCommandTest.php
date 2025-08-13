<?php

namespace Apogee\Watcher\Tests\Feature;

use Apogee\Watcher\Services\PSIClientService;
use Orchestra\Testbench\TestCase;
use Apogee\Watcher\WatcherServiceProvider;

class WatcherTestApiKeyCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
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
            ->expectsOutputToContain('Score: 91')
            ->assertExitCode(0);
    }
}