<?php

namespace Apogee\Watcher\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    private int $dailyLimit;
    private int $rateLimitPerMinute;
    private string $cachePrefix;

    public function __construct()
    {
        $this->dailyLimit = config('watcher.api_daily_limit', 25000);
        $this->rateLimitPerMinute = config('watcher.rate_limit_per_minute', 10);
        $this->cachePrefix = 'pagespeed_watcher:';
    }

    /**
     * Check if we can make an API request based on rate limits.
     *
     * @return bool
     */
    public function canMakeRequest(): bool
    {
        return $this->checkDailyLimit() && $this->checkMinuteLimit();
    }

    /**
     * Record an API request for rate limiting purposes.
     *
     * @return void
     */
    public function recordRequest(): void
    {
        $this->incrementDailyCount();
        $this->incrementMinuteCount();
    }

    /**
     * Get current usage statistics.
     *
     * @return array
     */
    public function getUsageStats(): array
    {
        $dailyKey = $this->getDailyKey();
        $minuteKey = $this->getMinuteKey();

        return [
            'daily_used' => Cache::get($dailyKey, 0),
            'daily_limit' => $this->dailyLimit,
            'daily_remaining' => max(0, $this->dailyLimit - Cache::get($dailyKey, 0)),
            'minute_used' => Cache::get($minuteKey, 0),
            'minute_limit' => $this->rateLimitPerMinute,
            'minute_remaining' => max(0, $this->rateLimitPerMinute - Cache::get($minuteKey, 0)),
        ];
    }

    /**
     * Check daily API limit.
     *
     * @return bool
     */
    private function checkDailyLimit(): bool
    {
        $dailyKey = $this->getDailyKey();
        $dailyCount = Cache::get($dailyKey, 0);

        if ($dailyCount >= $this->dailyLimit) {
            Log::warning('PageSpeed Watcher: Daily API limit reached', [
                'limit' => $this->dailyLimit,
                'used' => $dailyCount,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check per-minute rate limit.
     *
     * @return bool
     */
    private function checkMinuteLimit(): bool
    {
        $minuteKey = $this->getMinuteKey();
        $minuteCount = Cache::get($minuteKey, 0);

        if ($minuteCount >= $this->rateLimitPerMinute) {
            Log::warning('PageSpeed Watcher: Rate limit exceeded', [
                'limit_per_minute' => $this->rateLimitPerMinute,
                'used' => $minuteCount,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Increment daily request count.
     *
     * @return void
     */
    private function incrementDailyCount(): void
    {
        $dailyKey = $this->getDailyKey();
        Cache::increment($dailyKey);
        
        // Set expiration to end of current day
        $endOfDay = now()->endOfDay();
        Cache::put($dailyKey, Cache::get($dailyKey), $endOfDay);
    }

    /**
     * Increment minute request count.
     *
     * @return void
     */
    private function incrementMinuteCount(): void
    {
        $minuteKey = $this->getMinuteKey();
        Cache::increment($minuteKey);
        
        // Set expiration to end of current minute
        $endOfMinute = now()->endOfMinute();
        Cache::put($minuteKey, Cache::get($minuteKey), $endOfMinute);
    }

    /**
     * Get daily cache key.
     *
     * @return string
     */
    private function getDailyKey(): string
    {
        return $this->cachePrefix . 'daily:' . now()->format('Y-m-d');
    }

    /**
     * Get minute cache key.
     *
     * @return string
     */
    private function getMinuteKey(): string
    {
        return $this->cachePrefix . 'minute:' . now()->format('Y-m-d-H-i');
    }
}