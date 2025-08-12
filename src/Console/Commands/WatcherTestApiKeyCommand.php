<?php

namespace Apogee\Watcher\Console\Commands;

use Illuminate\Console\Command;
use Apogee\Watcher\Services\PSIClientService;
use RuntimeException;

class WatcherTestApiKeyCommand extends Command
{
    protected $signature = 'watcher:test-api-key {--strategy=mobile : Strategy to test: mobile or desktop}';

    protected $description = 'Validate connectivity to Google PageSpeed Insights using the configured API key.';

    public function handle(PSIClientService $psiClient): int
    {
        $apiKey = config('watcher.psi_api_key');
        if (empty($apiKey)) {
            $this->error('PSI_API_KEY is not set. Please configure your .env and config/watcher.php.');
            return self::FAILURE;
        }

        $appUrl = config('app.url') ?? env('APP_URL');
        if (empty($appUrl)) {
            $this->error('APP_URL is not set in your application configuration.');
            return self::FAILURE;
        }

        $strategy = $this->option('strategy');
        if (!in_array($strategy, ['mobile', 'desktop'], true)) {
            $this->error('Invalid strategy. Use "mobile" or "desktop".');
            return self::FAILURE;
        }

        $this->line("Testing PSI connectivity for {$appUrl} ({$strategy})...");

        try {
            $response = $psiClient->runTest($appUrl, $strategy);
            if (!is_array($response)) {
                throw new RuntimeException('Unexpected response from PSI API.');
            }

            $metrics = $psiClient->extractCoreMetrics($response);
            $scorePercent = isset($metrics['score']) ? (int) round($metrics['score'] * 100) : null;

            $this->info('OK: PSI API reachable.');
            if ($scorePercent !== null) {
                $this->line("Score: {$scorePercent}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error connecting to PSI API: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}