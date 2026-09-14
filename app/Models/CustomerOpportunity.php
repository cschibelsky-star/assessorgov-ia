<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOpportunity extends Model
{
    use HasFactory;

    public const STAGE_DETECTED = 'detected';
    public const STAGE_MATCHED = 'matched';
    public const STAGE_ANALYSIS = 'analysis';
    public const STAGE_STRATEGY = 'strategy';
    public const STAGE_PARTICIPATION = 'participation';
    public const STAGE_CLASSIFIED_WAITING = 'classified_waiting';
    public const STAGE_EXECUTION = 'execution';
    public const STAGE_FINANCIAL = 'financial';
    public const STAGE_COMPLETED = 'completed';

    protected $fillable = [
        'customer_id', 'opportunity_id', 'stage', 'match_score', 'match_reasons', 'gaps',
        'strategy', 'decision', 'classification_position', 'remanescente_eligible',
        'execution_status', 'financial_status', 'contracted_value', 'billed_value',
        'paid_value', 'last_financial_sync_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'match_reasons' => 'array',
            'gaps' => 'array',
            'strategy' => 'array',
            'remanescente_eligible' => 'boolean',
            'contracted_value' => 'decimal:2',
            'billed_value' => 'decimal:2',
            'paid_value' => 'decimal:2',
            'last_financial_sync_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
