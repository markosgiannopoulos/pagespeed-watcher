<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends Model
{
    public $timestamps = false;

    protected $table = 'watcher_test_results';

    protected $fillable = [
        'page_id',
        'strategy',
        'performance_score',
        'lcp_ms',
        'inp_ms',
        'cls',
        'fcp_ms',
        'tbt_ms',
        'speed_index_ms',
        'raw_json',
        'error_message',
        'status',
        'created_at',
    ];

    protected $casts = [
        'performance_score' => 'integer',
        'lcp_ms' => 'integer',
        'inp_ms' => 'integer',
        'cls' => 'decimal:3',
        'fcp_ms' => 'integer',
        'tbt_ms' => 'integer',
        'speed_index_ms' => 'integer',
        'raw_json' => 'array',
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}