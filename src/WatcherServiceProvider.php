<?php

namespace Apogee\Watcher;

use Illuminate\Support\ServiceProvider;
use Apogee\Watcher\Console\Commands\WatcherTestApiKeyCommand;
use Apogee\Watcher\Console\Commands\WatcherUsageCommand;
use Apogee\Watcher\Services\PSIClientService;
use GuzzleHttp\Client as GuzzleClient;

class WatcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/watcher.php', 'watcher');

        $this->app->singleton(PSIClientService::class, function ($app) {
            $guzzle = new GuzzleClient([
                'timeout' => 120,
                'connect_timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Laravel-PageSpeed-Watcher/1.0',
                ],
            ]);

            return new PSIClientService($guzzle, config('watcher.psi_api_key'));
        });
    }

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
    }
}