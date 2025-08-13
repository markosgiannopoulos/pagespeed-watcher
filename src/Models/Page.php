<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $table = 'watcher_pages';

    protected $fillable = [
        'url',
        'name',
        'active',
        'mobile_enabled',
        'desktop_enabled',
        'priority',
        'auto_discovered',
    ];

    protected $attributes = [
        'active' => true,
        'mobile_enabled' => true,
        'desktop_enabled' => true,
        'priority' => 5,
        'auto_discovered' => false,
    ];

    protected $casts = [
        'active' => 'boolean',
        'mobile_enabled' => 'boolean',
        'desktop_enabled' => 'boolean',
        'auto_discovered' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the test results for this page.
     * 
     * Returns a relationship to all PageSpeed Insights test results
     * that have been run for this specific page.
     * 
     * @return HasMany Relationship to test results
     */
    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class, 'page_id');
    }
}