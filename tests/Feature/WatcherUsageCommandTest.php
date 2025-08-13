<?php

namespace Apogee\Watcher\Tests\Feature;

use Apogee\Watcher\Models\WatcherApiUsage;
use Orchestra\Testbench\TestCase;
use Apogee\Watcher\WatcherServiceProvider;
use Carbon\Carbon;

class WatcherUsageCommandTest extends TestCase
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
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    /**
     * Set up the test environment.
     * 
     * Runs migrations to ensure the database schema is available for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load package migrations for testing
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_command_shows_no_usage_when_empty(): void
    {
        $this->artisan('watcher:usage')
            ->expectsOutputToContain('No usage recorded yet.')
            ->assertExitCode(0);
    }

    public function test_command_shows_today_usage(): void
    {
        // Create today's usage record
        WatcherApiUsage::create([
            'date' => Carbon::today(),
            'requests_total' => 10,
            'requests_ok' => 8,
            'requests_error' => 2,
            'cost_usd_estimate' => 0.004,
        ]);

        $this->artisan('watcher:usage')
            ->expectsOutputToContain('Today:')
            ->expectsOutputToContain('Total Requests: 10')
            ->expectsOutputToContain('Successful: 8')
            ->expectsOutputToContain('Errors: 2')
            ->expectsOutputToContain('Cost Estimate: $0.004')
            ->assertExitCode(0);
    }

    public function test_command_shows_last_7_days(): void
    {
        // Create usage records for today and yesterday
        WatcherApiUsage::create([
            'date' => Carbon::today(),
            'requests_total' => 10,
            'requests_ok' => 8,
            'requests_error' => 2,
            'cost_usd_estimate' => 0.004,
        ]);

        WatcherApiUsage::create([
            'date' => Carbon::yesterday(),
            'requests_total' => 15,
            'requests_ok' => 12,
            'requests_error' => 3,
            'cost_usd_estimate' => 0.006,
        ]);

        $this->artisan('watcher:usage')
            ->expectsOutputToContain('Today:')
            ->expectsOutputToContain('Total Requests: 10')
            ->expectsOutputToContain('Last 7 Days:')
            ->expectsOutputToContain('Total Requests: 25')
            ->expectsOutputToContain('Successful: 20')
            ->expectsOutputToContain('Errors: 5')
            ->expectsOutputToContain('Total Cost Estimate: $0.010')
            ->assertExitCode(0);
    }
}