<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;

    public const CHANNEL_LICITACAO = 'licitacao';
    public const CHANNEL_IRP = 'irp';
    public const CHANNEL_SICX = 'sicx';
    public const CHANNEL_REMANESCENTE = 'remanescente';
    public const CHANNEL_FOMENTO = 'fomento';

    protected $fillable = [
        'channel', 'source_name', 'source_type', 'source_url', 'external_id', 'title',
        'summary', 'organization', 'jurisdiction', 'state', 'municipalities',
        'estimated_value', 'opens_at', 'closes_at', 'event_at', 'requirements',
        'required_documents', 'metadata', 'status', 'source_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'municipalities' => 'array',
            'requirements' => 'array',
            'required_documents' => 'array',
            'metadata' => 'array',
            'estimated_value' => 'decimal:2',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'event_at' => 'datetime',
            'source_checked_at' => 'datetime',
        ];
    }

    public function customerOpportunities(): HasMany
    {
        return $this->hasMany(CustomerOpportunity::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OpportunityEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q): void {
                $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }

    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }
}
