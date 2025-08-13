<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'active' => 'boolean',
        'mobile_enabled' => 'boolean',
        'desktop_enabled' => 'boolean',
        'auto_discovered' => 'boolean',
        'priority' => 'integer',
    ];
}