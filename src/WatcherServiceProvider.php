<?php

namespace Apogee\Watcher;

use Illuminate\Support\ServiceProvider;
use Apogee\Watcher\Console\Commands\WatcherTestApiKeyCommand;
use Apogee\Watcher\Console\Commands\WatcherUsageCommand;
use Apogee\Watcher\Services\PSIClientService;
use GuzzleHttp\Client as GuzzleClient;

class WatcherServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * 
     * This method is called during the service container binding phase.
     * It registers the PSI client service and merges the package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/watcher.php', 'watcher');

        $this->app->singleton(RateLimitService::class, function ($app) {
            return new RateLimitService();
        });

        $this->app->singleton(PSIClientService::class, function ($app) {
            $config = config('watcher.http_client', []);
            
            $guzzle = new GuzzleClient([
                'timeout' => $config['timeout'] ?? 120,
                'connect_timeout' => $config['connect_timeout'] ?? 15,
                'headers' => [
                    'User-Agent' => 'Laravel-PageSpeed-Watcher/1.0',
                ],
            ]);

            return new PSIClientService(
                $guzzle, 
                config('watcher.psi_api_key'),
                $app->make(RateLimitService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     * 
     * This method is called after all services are registered.
     * It publishes configuration files, migrations, and registers console commands.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/watcher.php' => config_path('watcher.php'),
        ], 'watcher-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'watcher-migrations');

        // Register commands only when running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                WatcherTestApiKeyCommand::class,
                WatcherUsageCommand::class,
            ]);
        }

        // Load migrations from package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Validate configuration
        $this->validateConfiguration();
    }
    
    /**
     * Validate the package configuration.
     * 
     * Checks for required configuration values and validates threshold settings.
     * Logs warnings for missing API keys and throws exceptions for invalid thresholds.
     */
    private function validateConfiguration(): void
    {
        $config = config('watcher');
        
        if (empty($config['psi_api_key'])) {
            // Log warning but don't fail - API key might be set later
            if (app()->runningInConsole()) {
                $this->app['log']->warning('PageSpeed Watcher: PSI_API_KEY not configured');
            }
        }
        
        // Validate thresholds
        $thresholds = $config['thresholds'] ?? [];
        if (isset($thresholds['excellent']) && isset($thresholds['good']) && isset($thresholds['needs_improvement'])) {
            if ($thresholds['excellent'] <= $thresholds['good'] || $thresholds['good'] <= $thresholds['needs_improvement']) {
                throw new \InvalidArgumentException('Invalid performance thresholds configuration');
            }
        }
    }
}