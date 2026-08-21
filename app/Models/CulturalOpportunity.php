<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CulturalOpportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_name', 'source_type', 'source_url', 'external_id', 'title', 'summary',
        'organization', 'opportunity_type', 'state', 'municipalities', 'cultural_areas',
        'eligible_legal_profiles', 'funding_min', 'funding_max', 'opens_at', 'closes_at',
        'eligibility_rules', 'required_documents', 'metadata', 'status', 'source_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'municipalities' => 'array',
            'cultural_areas' => 'array',
            'eligible_legal_profiles' => 'array',
            'eligibility_rules' => 'array',
            'required_documents' => 'array',
            'metadata' => 'array',
            'funding_min' => 'decimal:2',
            'funding_max' => 'decimal:2',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'source_checked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q): void {
                $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }

    public function scopeSaoPaulo(Builder $query): Builder
    {
        return $query->where('state', 'SP');
    }
}
