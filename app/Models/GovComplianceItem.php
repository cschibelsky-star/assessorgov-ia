<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovComplianceItem extends Model
{
    protected $fillable = [
        'customer_id',
        'item_key',
        'title',
        'action',
        'priority',
        'impact_class',
        'target',
        'status',
        'note',
        'applied_at',
        'applied_by_user_id',
        'submitted_at',
        'resolved_at',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerOpportunity::class,
            'gov_compliance_item_opportunities',
        )->withTimestamps();
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(GovComplianceEvidence::class);
    }

    public function latestEvidence()
    {
        return $this->hasOne(GovComplianceEvidence::class)->latestOfMany();
    }
}
