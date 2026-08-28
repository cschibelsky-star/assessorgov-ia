<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public const BILLING_PENDING = 'pending';
    public const BILLING_ACTIVE = 'active';
    public const BILLING_PAST_DUE = 'past_due';
    public const BILLING_SUSPENDED = 'suspended';
    public const BILLING_CANCELLED = 'cancelled';

    public const OPERATIONAL_NORMAL = 'normal';
    public const OPERATIONAL_GRACE = 'grace';
    public const OPERATIONAL_RESTRICTED = 'restricted';
    public const OPERATIONAL_PRESERVATION = 'preservation';

    protected $fillable = [
        'customer_id',
        'plan_id',
        'status',
        'billing_status',
        'operational_status',
        'asaas_subscription_id',
        'asaas_payment_id',
        'activated_at',
        'expires_at',
        'cancelled_at',
        'past_due_at',
        'grace_until',
        'restricted_at',
        'preservation_at',
        'suspended_at',
        'reactivated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'past_due_at' => 'datetime',
            'grace_until' => 'datetime',
            'restricted_at' => 'datetime',
            'preservation_at' => 'datetime',
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->billing_status === self::BILLING_ACTIVE
            && $this->operational_status === self::OPERATIONAL_NORMAL
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function preservesExistingWork(): bool
    {
        return in_array($this->operational_status, [
            self::OPERATIONAL_NORMAL,
            self::OPERATIONAL_GRACE,
            self::OPERATIONAL_RESTRICTED,
            self::OPERATIONAL_PRESERVATION,
        ], true);
    }

    public function canCreateNewWork(): bool
    {
        return in_array($this->operational_status, [
            self::OPERATIONAL_NORMAL,
            self::OPERATIONAL_GRACE,
        ], true);
    }

    public function canUseAi(): bool
    {
        return $this->operational_status === self::OPERATIONAL_NORMAL;
    }
}
