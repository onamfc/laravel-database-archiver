<?php

namespace LaravelDbArchiver\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveLog extends Model
{
    protected $fillable = [
        'table_name',
        'status',
        'total_records',
        'archived_count',
        'duration',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}