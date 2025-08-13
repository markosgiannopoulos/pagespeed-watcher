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
        'lcp',
        'inp',
        'cls',
        'fcp',
        'tbt',
        'speed_index',
        'raw_json',
        'error_message',
        'status',
        'created_at',
    ];

    protected $casts = [
        'performance_score' => 'integer',
        'lcp' => 'integer',
        'inp' => 'integer',
        'cls' => 'decimal:3',
        'fcp' => 'integer',
        'tbt' => 'integer',
        'speed_index' => 'integer',
        'raw_json' => 'array',
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}