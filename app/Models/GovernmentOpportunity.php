<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentOpportunity extends Model
{
    protected $fillable = [
        'source',
        'source_module',
        'external_id',
        'title',
        'summary',
        'status',
        'state',
        'municipality',
        'organization',
        'instrument_type',
        'amount_min',
        'amount_max',
        'opens_at',
        'closes_at',
        'source_url',
        'fetched_at',
        'raw_payload_hash',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'fetched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
