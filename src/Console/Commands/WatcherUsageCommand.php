<?php

namespace Apogee\Watcher\Console\Commands;

use Illuminate\Console\Command;
use Apogee\Watcher\Models\WatcherApiUsage;
use Carbon\Carbon;

class WatcherUsageCommand extends Command
{
    protected $signature = 'watcher:usage';

    protected $description = 'Display PageSpeed Insights API usage statistics.';

    public function handle(): int
    {
        $this->info('PageSpeed Insights API Usage Statistics');
        $this->line('');

        // Get today's usage
        $today = Carbon::today();
        $todayUsage = WatcherApiUsage::where('date', $today)->first();

        if (!$todayUsage) {
            $this->line('No usage recorded yet.');
            return self::SUCCESS;
        }

        // Display today's stats
        $this->line('Today:');
        $this->line("  Total Requests: {$todayUsage->requests_total}");
        $this->line("  Successful: {$todayUsage->requests_ok}");
        $this->line("  Errors: {$todayUsage->requests_error}");
        $this->line("  Cost Estimate: \${$todayUsage->cost_usd_estimate}");
        $this->line('');

        // Get last 7 days
        $sevenDaysAgo = Carbon::today()->subDays(6);
        $lastWeekUsage = WatcherApiUsage::whereBetween('date', [$sevenDaysAgo, $today])
            ->orderBy('date')
            ->get();

        if ($lastWeekUsage->count() > 1) {
            $this->line('Last 7 Days:');
            
            $totalRequests = $lastWeekUsage->sum('requests_total');
            $totalSuccessful = $lastWeekUsage->sum('requests_ok');
            $totalErrors = $lastWeekUsage->sum('requests_error');
            $totalCost = $lastWeekUsage->sum('cost_usd_estimate');

            $this->line("  Total Requests: {$totalRequests}");
            $this->line("  Successful: {$totalSuccessful}");
            $this->line("  Errors: {$totalErrors}");
            $this->line("  Total Cost Estimate: \${$totalCost}");
        }

        $this->line('');

        // Show daily limit information
        $dailyLimit = config('watcher.api_daily_limit', 25000);
        $this->line("Daily Limit: {$dailyLimit} requests");
        
        if ($todayUsage) {
            $remaining = max(0, $dailyLimit - $todayUsage->requests_total);
            $this->line("Remaining Today: {$remaining} requests");
            
            if ($remaining < 100) {
                $this->warn('⚠️  Daily limit nearly reached');
            }
        }

        // Recommendations
        if ($todayUsage && $todayUsage->requests_error > 0) {
            $this->line('');
            $this->warn('Recommendation: Check for API errors in your configuration');
        }
        
        if ($todayUsage && $todayUsage->cost_usd_estimate > 0) {
            $this->line('');
            $this->warn('Recommendation: Consider reducing test frequency to avoid costs');
        }

        return self::SUCCESS;
    }
}