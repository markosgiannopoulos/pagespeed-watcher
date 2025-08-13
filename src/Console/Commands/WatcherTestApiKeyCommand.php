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

        $result = $psiClient->testApiKey($appUrl, $strategy);

        if ($result['http_code'] === 200) {
            $this->info("HTTP Code: {$result['http_code']}");
            if ($result['score'] !== null) {
                $this->line("Performance Score: {$result['score']}");
            }
            return self::SUCCESS;
        } else {
            $this->error("HTTP Code: {$result['http_code']}");
            $this->error("Error: {$result['error']}");
            return self::FAILURE;
        }
    }
}