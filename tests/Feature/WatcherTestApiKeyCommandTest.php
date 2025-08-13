<?php

namespace Apogee\Watcher\Tests\Feature;

use Apogee\Watcher\Services\PSIClientService;
use Orchestra\Testbench\TestCase;
use Apogee\Watcher\WatcherServiceProvider;

/**
 * Feature tests for the watcher:test-api-key command.
 * 
 * These tests verify the functionality of the PageSpeed Insights API key testing command.
 * They cover various scenarios including successful API calls, error handling,
 * different strategies (mobile/desktop), and various API response types.
 * 
 * The tests use mocked PSIClientService instances to avoid making actual API calls
 * while still testing the complete command flow and output formatting.
 */
class WatcherTestApiKeyCommandTest extends TestCase
{
    /**
     * Get the package providers for testing.
     * 
     * @param mixed $app The application instance
     * @return array Array of service provider classes
     */
    protected function getPackageProviders($app)
    {
        /** @SuppressWarnings("UnusedFormalParameter") */
        return [WatcherServiceProvider::class];
    }

    /**
     * Define the test environment.
     * 
     * Sets up the test environment with required configuration values.
     * 
     * @param mixed $app The application instance
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('watcher.psi_api_key', 'test_key');
        $app['config']->set('app.url', 'https://example.com');
    }

    /**
     * Create a mock PSI client service for testing.
     * 
     * Creates an anonymous class that extends PSIClientService with the proper
     * constructor signature including the RateLimitService dependency.
     * This helper method is used to create consistent mock instances across tests.
     * 
     * @param string|null $apiKey The API key to use (defaults to 'test_key')
     * @return PSIClientService A mock service instance with proper constructor
     */
    private function createMockPSIClient(?string $apiKey = 'test_key'): PSIClientService
    {
        return new class(new \GuzzleHttp\Client(), $apiKey) extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
        };
    }

    /**
     * Test that the command reports successful API connectivity and performance score.
     * 
     * Verifies that when the API key is valid and the request succeeds,
     * the command outputs the correct HTTP code and performance score.
     * This test uses a simple mock that returns a fixed success response.
     */
    public function test_command_reports_ok_and_score(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles detailed Lighthouse response data correctly.
     * 
     * Verifies that the command can process a full Lighthouse response with
     * performance metrics and extract the performance score correctly.
     * This test ensures the metric extraction functionality works properly.
     */
    public function test_command_with_detailed_lighthouse_response(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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
            
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                $response = $this->runTest($url, $strategy);
                $metrics = $this->extractCoreMetrics($response);
                $score = isset($metrics['score']) ? (int) round($metrics['score'] * 100) : null;
                
                return [
                    'http_code' => 200,
                    'score' => $score,
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

    /**
     * Test that the command works correctly with desktop strategy.
     * 
     * Verifies that the command accepts and processes the --strategy=desktop option
     * correctly, ensuring that different testing strategies are supported.
     */
    public function test_command_with_desktop_strategy(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles missing API key correctly.
     * 
     * Verifies that when no API key is configured, the command returns
     * the appropriate error message and exit code.
     */
    public function test_command_without_api_key(): void
    {
        $this->app['config']->set('watcher.psi_api_key', null);

        $fake = new class(new \GuzzleHttp\Client(), null) extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles poor performance scores correctly.
     * 
     * Verifies that the command can display performance scores for pages
     * with poor performance (low scores) and handles the output formatting correctly.
     */
    public function test_command_with_poor_performance(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles poor performance with detailed Lighthouse metrics.
     * 
     * Verifies that the command can process detailed Lighthouse response data
     * for pages with poor performance and extract the correct performance score.
     * This test ensures the metric extraction works for low-performance scenarios.
     */
    public function test_command_with_poor_performance_detailed_metrics(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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
            
            public function testApiKey(string $url, string $strategy = 'mobile'): array {
                $response = $this->runTest($url, $strategy);
                $metrics = $this->extractCoreMetrics($response);
                $score = isset($metrics['score']) ? (int) round($metrics['score'] * 100) : null;
                
                return [
                    'http_code' => 200,
                    'score' => $score,
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

    /**
     * Test that the command handles API errors correctly.
     * 
     * Verifies that when the API returns an error response (e.g., 400 Bad Request),
     * the command displays the appropriate error message and exit code.
     */
    public function test_command_with_api_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles missing APP_URL configuration correctly.
     * 
     * Verifies that when the APP_URL environment variable is not set,
     * the command displays an appropriate error message and exits with failure code.
     */
    public function test_command_without_app_url(): void
    {
        // Set an invalid URL format to test URL validation
        $this->app['config']->set('app.url', 'invalid-url');

        $this->artisan('watcher:test-api-key')
            ->expectsOutputToContain('Invalid APP_URL format')
            ->assertExitCode(1);
    }

    /**
     * Test that the command validates strategy parameter correctly.
     * 
     * Verifies that when an invalid strategy is provided (not 'mobile' or 'desktop'),
     * the command displays an appropriate error message and exits with failure code.
     */
    public function test_command_with_invalid_strategy(): void
    {
        $this->artisan('watcher:test-api-key', ['--strategy' => 'invalid'])
            ->expectsOutputToContain('Invalid strategy')
            ->assertExitCode(1);
    }

    /**
     * Test that the command handles rate limit errors correctly.
     * 
     * Verifies that when the API returns a 429 rate limit error,
     * the command displays the appropriate error message and exit code.
     */
    public function test_command_with_rate_limit_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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

    /**
     * Test that the command handles server errors correctly.
     * 
     * Verifies that when the API returns a 5xx server error,
     * the command displays the appropriate error message and exit code.
     */
    public function test_command_with_server_error(): void
    {
        $fake = new class(new \GuzzleHttp\Client(), 'test_key') extends PSIClientService {
            public function __construct($client, $key) { 
                $rateLimitService = new \Apogee\Watcher\Services\RateLimitService();
                parent::__construct($client, $key, $rateLimitService); 
            }
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