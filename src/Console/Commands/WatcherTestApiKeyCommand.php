<?php

namespace Apogee\Watcher\Console\Commands;

use Illuminate\Console\Command;
use Apogee\Watcher\Services\PSIClientService;
use RuntimeException;

class WatcherTestApiKeyCommand extends Command
{
    protected $signature = 'watcher:test-api-key
        {--strategy=mobile : Strategy to test: mobile or desktop}
        {--url= : URL to test (defaults to app.url)}';

    protected $description = 'Validate connectivity to Google PageSpeed Insights using the configured API key.';

    public function handle(PSIClientService $psiClient): int
    {
        $apiKey = config('watcher.psi_api_key');
        if (empty($apiKey)) {
            $this->error('PSI_API_KEY is not set');
            return self::FAILURE;
        }

        $appUrl = $this->option('url') ?: (config('app.url') ?? env('APP_URL'));
        if (empty($appUrl)) {
            $this->error('APP_URL is not set');
            return self::FAILURE;
        }

        // Validate URL format
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $this->error('Invalid APP_URL format. Please provide a valid URL.');
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

            $metrics = $psiClient->extractCoreMetrics($response);
            $scorePercent = isset($metrics['score']) ? (int) round($metrics['score'] * 100) : null;

            $this->info('OK: PSI API reachable.');
            if ($scorePercent !== null) {
                $this->line("Score: {$scorePercent}%");
                
                // Provide performance feedback
                if ($scorePercent >= 90) {
                    $this->info('Performance: Excellent');
                } elseif ($scorePercent >= 70) {
                    $this->warn('Performance: Good');
                } else {
                    $this->error('Performance: Needs improvement');
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error connecting to PSI API: ' . $e->getMessage());
            
            // Provide more specific error guidance
            if (str_contains($e->getMessage(), 'API key')) {
                $this->line('Please check your PSI_API_KEY configuration.');
            } elseif (str_contains($e->getMessage(), 'quota')) {
                $this->line('You may have exceeded your daily API quota.');
            }
            
            return self::FAILURE;
        }
    }
}