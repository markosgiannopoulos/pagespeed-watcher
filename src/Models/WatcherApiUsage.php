<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;

class WatcherApiUsage extends Model
{
    protected $table = 'watcher_api_usage';
    
    protected $fillable = [
        'date',
        'requests_total',
        'requests_ok',
        'requests_error',
        'cost_usd_estimate',
    ];

    protected $casts = [
        'date' => 'date',
        'requests_total' => 'integer',
        'requests_ok' => 'integer',
        'requests_error' => 'integer',
        'cost_usd_estimate' => 'decimal:4',
    ];

    /**
     * Get today's usage record, creating it if it doesn't exist.
     * 
     * Retrieves or creates a usage record for the current date.
     * This ensures we always have a record to track daily API usage.
     * 
     * @return self The usage record for today
     */
    public static function getTodayRecord(): self
    {
        return static::firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'requests_total' => 0,
                'requests_ok' => 0,
                'requests_error' => 0,
                'cost_usd_estimate' => 0,
            ]
        );
    }

    /**
     * Increment request counters and update cost estimate.
     * 
     * Updates the usage statistics for today's API requests and calculates
     * the cost estimate based on requests exceeding the daily limit.
     * 
     * @param bool $wasSuccessful Whether the request was successful (true) or failed (false)
     */
    public function incrementRequests(bool $wasSuccessful = true): void
    {
        $this->increment('requests_total');
        
        if ($wasSuccessful) {
            $this->increment('requests_ok');
        } else {
            $this->increment('requests_error');
        }

        // Calculate cost estimate: max(0, requests_total - daily_limit) * psi_cost_per_request
        $dailyLimit = config('watcher.api_daily_limit', 25000);
        $excessRequests = max(0, $this->requests_total - $dailyLimit);
        $costPerRequest = (float) config('watcher.psi_cost_per_request', 0.002);
        $this->cost_usd_estimate = $excessRequests * $costPerRequest;
        
        $this->save();
    }
}