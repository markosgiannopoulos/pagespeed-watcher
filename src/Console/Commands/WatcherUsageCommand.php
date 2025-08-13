<?php

namespace Apogee\Watcher\Console\Commands;

use Illuminate\Console\Command;
use Apogee\Watcher\Services\RateLimitService;

class WatcherUsageCommand extends Command
{
    protected $signature = 'watcher:usage';

    protected $description = 'Display PageSpeed Insights API usage statistics.';

    public function handle(RateLimitService $rateLimitService): int
    {
        $stats = $rateLimitService->getUsageStats();

        $this->info('PageSpeed Insights API Usage Statistics');
        $this->line('');

        // Daily usage
        $this->line('Daily Usage:');
        $this->line("  Used: {$stats['daily_used']} / {$stats['daily_limit']}");
        $this->line("  Remaining: {$stats['daily_remaining']}");
        
        // Show progress bar for daily usage
        $dailyPercentage = ($stats['daily_used'] / $stats['daily_limit']) * 100;
        $this->line("  Progress: " . number_format($dailyPercentage, 1) . '%');
        
        if ($dailyPercentage > 80) {
            $this->warn('  ⚠️  Daily limit nearly reached');
        }

        $this->line('');

        // Minute usage
        $this->line('Rate Limit (per minute):');
        $this->line("  Used: {$stats['minute_used']} / {$stats['minute_limit']}");
        $this->line("  Remaining: {$stats['minute_remaining']}");
        
        if ($stats['minute_remaining'] === 0) {
            $this->error('  ❌ Rate limit reached - wait before making more requests');
        }

        $this->line('');

        // Recommendations
        if ($stats['daily_remaining'] < 100) {
            $this->warn('Recommendation: Consider reducing test frequency or upgrading API quota');
        }

        if ($stats['minute_remaining'] === 0) {
            $this->warn('Recommendation: Implement delays between requests to avoid rate limiting');
        }

        return self::SUCCESS;
    }
}