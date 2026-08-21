<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CulturalSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'owner',
        'scope',
        'state',
        'municipality',
        'url',
        'source_type',
        'ingestion_mode',
        'priority',
        'enabled',
        'last_checked_at',
        'last_success_at',
        'last_status',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
