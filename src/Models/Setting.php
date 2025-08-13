<?php

namespace Apogee\Watcher\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

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