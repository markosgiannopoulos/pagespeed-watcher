<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Uses an upsert to ensure the row exists and atomic increments to avoid races.
     * 
     * @param bool $wasSuccessful Whether the request was successful (true) or failed (false)
     */
    public function incrementRequests(bool $wasSuccessful = true): void
    {
        $today = now();
        $dateString = $today->toDateString();

        $dailyLimit = (int) config('watcher.api_daily_limit', 25000);
        $costPerRequest = (float) config('watcher.psi_cost_per_request', 0.002);

        // Ensure row exists for today
        DB::table($this->table)->upsert([
            'date' => $dateString,
            'requests_total' => 0,
            'requests_ok' => 0,
            'requests_error' => 0,
            'cost_usd_estimate' => 0,
            'created_at' => $today,
            'updated_at' => $today,
        ], ['date'], ['updated_at']);

        $okIncrement = $wasSuccessful ? 1 : 0;
        $errorIncrement = $wasSuccessful ? 0 : 1;

        // Atomic increments and cost recomputation in a single UPDATE
        DB::table($this->table)
            ->where('date', $dateString)
            ->update([
                'requests_total' => DB::raw('requests_total + 1'),
                'requests_ok' => DB::raw('requests_ok + ' . $okIncrement),
                'requests_error' => DB::raw('requests_error + ' . $errorIncrement),
                // cost = max(0, (requests_total + 1) - daily_limit) * cost_per_request
                'cost_usd_estimate' => DB::raw('(
                    GREATEST(0, requests_total + 1 - ' . $dailyLimit . ')
                ) * ' . $costPerRequest),
                'updated_at' => $today,
            ]);

        // Refresh in-memory model if this instance represents today's record
        if ($this->date && $this->date->toDateString() === $dateString) {
            $this->refresh();
        }
    }
}