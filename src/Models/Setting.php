<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string      $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Setting extends Model
{
    public $timestamps = true;

    protected $table = 'watcher_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}