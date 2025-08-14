<?php

namespace Apogee\Watcher\Console\Commands;

use Illuminate\Console\Command;
use Apogee\Watcher\Services\PSIClientService;
use RuntimeException;

class WatcherTestPageCommand extends Command
{
    protected $signature = 'watcher:test-page
        {--strategy=mobile : Strategy to test: mobile or desktop}
        {--url= : URL to test (defaults to app.url)}';

    protected $description = 'Run a PageSpeed Insights test to validate API connectivity and analyze page performance.';

    /**
     * Execute the console command.
     * 
     * Runs a PageSpeed Insights test for the specified URL using the configured
     * API key. Validates connectivity, performs a full performance analysis,
     * and displays the results including performance score and any errors.
     * 
     * @param PSIClientService $psiClient The PSI client service for making API requests
     * @return int Command exit code (0 for success, 1 for failure)
     */
    public function handle(PSIClientService $psiClient): int
    {
        $appUrl = $this->option('url');
        if (empty($appUrl)) {
            $appUrl = config('app.url') ?: env('APP_URL');
        }
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

        $result = $psiClient->testPage($appUrl, $strategy);

        if ($result['http_code'] === 200) {
            $this->info("HTTP Code: {$result['http_code']}");
            if ($result['score'] !== null) {
                $this->line("Performance Score: {$result['score']}");
                
                // Provide performance feedback
                $thresholds = config('watcher.thresholds', []);
                $excellent = $thresholds['excellent'] ?? 90;
                $good = $thresholds['good'] ?? 70;
                
                if ($result['score'] >= $excellent) {
                    $this->info('Performance: Excellent');
                } elseif ($result['score'] >= $good) {
                    $this->warn('Performance: Good');
                } else {
                    $this->error('Performance: Needs improvement');
                }
            }
            return self::SUCCESS;
        } else {
            $this->error("HTTP Code: {$result['http_code']}");
            $this->error("Error: {$result['error']}");
            
            // Provide more specific error guidance
            if (str_contains($result['error'], 'API key')) {
                $this->line('Please check your PSI_API_KEY configuration.');
            } elseif (str_contains($result['error'], 'quota') || str_contains($result['error'], '429')) {
                $this->line('You may have exceeded your daily API quota.');
            }
            
            return self::FAILURE;
        }
    }
}