<?php

namespace Apogee\Watcher\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Apogee\Watcher\Services\PSIClientService;
use Apogee\Watcher\WatcherServiceProvider;

/**
 * Feature tests for the watcher:test-page command.
 * 
 * Tests the watcher:test-page command functionality including success scenarios,
 * error handling, option parsing, and integration with the PSI client service.
 */
class WatcherTestPageCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get the package providers for testing.
     * 
     * @param mixed $app The application instance
     * @return array Array of service provider classes
     */
    protected function getPackageProviders($app)
    {
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
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('watcher.api_daily_limit', 25000);
        $app['config']->set('watcher.psi_cost_per_request', 0.002);
        $app['config']->set('watcher.thresholds.excellent', 90);
        $app['config']->set('watcher.thresholds.good', 70);
    }

    /**
     * Test successful test command execution.
     */
    public function test_successful_test_command_execution(): void
    {
        // Mock PSI client service
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 200,
                'score' => 85,
                'error' => null,
            ]);

        $this->app->instance(PSIClientService::class, $mockPsiClient);

        // Define an anonymous class that extends PSIClientService
        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command
        $this->artisan('watcher:test-page')
            ->expectsOutput('Testing PSI connectivity for https://example.com (mobile)...')
            ->expectsOutput('HTTP Code: 200')
            ->expectsOutput('Performance Score: 85')
            ->expectsOutput('Performance: Good')
            ->assertExitCode(0);
    }

    /**
     * Test command with excellent performance score.
     */
    public function test_command_with_excellent_performance_score(): void
    {
        // Mock PSI client service
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 200,
                'score' => 95,
                'error' => null,
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command
        $this->artisan('watcher:test-page')
            ->expectsOutput('Performance: Excellent')
            ->assertExitCode(0);
    }

    /**
     * Test command with desktop strategy option.
     */
    public function test_command_with_desktop_strategy_option(): void
    {
        // Mock PSI client service
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'desktop')
            ->willReturn([
                'http_code' => 200,
                'score' => 90,
                'error' => null,
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command with desktop strategy
        $this->artisan('watcher:test-page', ['--strategy' => 'desktop'])
            ->expectsOutput('Testing PSI connectivity for https://example.com (desktop)...')
            ->assertExitCode(0);
    }

    /**
     * Test command with poor performance score.
     */
    public function test_command_with_poor_performance_score(): void
    {
        // Mock PSI client service
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 200,
                'score' => 45,
                'error' => null,
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command
        $this->artisan('watcher:test-page')
            ->expectsOutput('Performance: Needs improvement')
            ->assertExitCode(0);
    }

    /**
     * Test command failure with HTTP error.
     */
    public function test_command_failure_with_http_error(): void
    {
        // Mock PSI client service to return error
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 403,
                'score' => null,
                'error' => 'API key error: Invalid API key',
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command expecting failure
        $this->artisan('watcher:test-page')
            ->expectsOutput('HTTP Code: 403')
            ->expectsOutput('Error: API key error: Invalid API key')
            ->expectsOutput('Please check your PSI_API_KEY configuration.')
            ->assertExitCode(1);
    }

    /**
     * Test command failure with quota exceeded.
     */
    public function test_command_failure_with_quota_exceeded(): void
    {
        // Mock PSI client service to return quota error
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 429,
                'score' => null,
                'error' => 'quota exceeded or rate limited (429)',
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command expecting failure
        $this->artisan('watcher:test-page')
            ->expectsOutput('You may have exceeded your daily API quota.')
            ->assertExitCode(1);
    }

    /**
     * Test command with missing APP_URL configuration.
     */
    public function test_command_with_missing_app_url(): void
    {
        // Override the app configuration to remove URL
        $this->app['config']->set('app.url', null);
        
        // Execute command expecting failure
        $this->artisan('watcher:test-page')
            ->expectsOutput('APP_URL is not set')
            ->assertExitCode(1);
    }

    /**
     * Test command with invalid strategy option.
     */
    public function test_command_with_invalid_strategy(): void
    {
        // Execute command with invalid strategy
        $this->artisan('watcher:test-page', ['--strategy' => 'invalid'])
            ->expectsOutput('Invalid strategy. Use "mobile" or "desktop".')
            ->assertExitCode(1);
    }

    /**
     * Test command with null performance score.
     */
    public function test_command_with_null_performance_score(): void
    {
        // Mock PSI client service to return null score
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://example.com', 'mobile')
            ->willReturn([
                'http_code' => 200,
                'score' => null,
                'error' => null,
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command
        $this->artisan('watcher:test-page')
            ->expectsOutput('HTTP Code: 200')
            ->assertExitCode(0);
    }

    /**
     * Test command with custom URL option.
     */
    public function test_command_with_custom_url_option(): void
    {
        // Mock PSI client service to expect custom URL
        $mockPsiClient = $this->createMock(PSIClientService::class);
        $mockPsiClient->expects($this->once())
            ->method('testPage')
            ->with('https://custom.example.com', 'mobile')
            ->willReturn([
                'http_code' => 200,
                'score' => 80,
                'error' => null,
            ]);

        $this->app->bind(PSIClientService::class, function () use ($mockPsiClient) {
            return new class($mockPsiClient) extends PSIClientService {
                private $mock;
                
                public function __construct($mock) {
                    $this->mock = $mock;
                }
                
                public function testPage(string $url, string $strategy = 'mobile'): array {
                    return $this->mock->testPage($url, $strategy);
                }
            };
        });

        // Execute command with custom URL
        $this->artisan('watcher:test-page', ['--url' => 'https://custom.example.com'])
            ->expectsOutput('Testing PSI connectivity for https://custom.example.com (mobile)...')
            ->assertExitCode(0);
    }
}