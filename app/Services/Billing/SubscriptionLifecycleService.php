<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

class SubscriptionLifecycleService
{
    public function markPaid(Subscription $subscription): Subscription
    {
        $now = CarbonImmutable::now();

        $subscription->forceFill([
            'status' => 'active',
            'billing_status' => Subscription::BILLING_ACTIVE,
            'operational_status' => Subscription::OPERATIONAL_NORMAL,
            'activated_at' => $subscription->activated_at ?? $now,
            'past_due_at' => null,
            'grace_until' => null,
            'restricted_at' => null,
            'preservation_at' => null,
            'suspended_at' => null,
            'reactivated_at' => $subscription->billing_status === Subscription::BILLING_PAST_DUE ? $now : $subscription->reactivated_at,
        ])->save();

        return $subscription->refresh();
    }

    public function markPastDue(Subscription $subscription, ?CarbonImmutable $occurredAt = null): Subscription
    {
        $now = $occurredAt ?? CarbonImmutable::now();
        $pastDueAt = $subscription->past_due_at?->toImmutable() ?? $now;

        $subscription->forceFill([
            'billing_status' => Subscription::BILLING_PAST_DUE,
            'past_due_at' => $pastDueAt,
        ])->save();

        return $this->recalculateOperationalStatus($subscription->refresh(), $now);
    }

    public function recalculateOperationalStatus(Subscription $subscription, ?CarbonImmutable $now = null): Subscription
    {
        $now ??= CarbonImmutable::now();

        if ($subscription->billing_status === Subscription::BILLING_ACTIVE) {
            return $this->markPaid($subscription);
        }

        if ($subscription->billing_status !== Subscription::BILLING_PAST_DUE || $subscription->past_due_at === null) {
            return $subscription;
        }

        $daysPastDue = $subscription->past_due_at->startOfDay()->diffInDays($now->startOfDay());

        if ($daysPastDue <= 7) {
            $subscription->forceFill([
                'operational_status' => Subscription::OPERATIONAL_GRACE,
                'grace_until' => $subscription->past_due_at->copy()->addDays(7)->endOfDay(),
            ])->save();

            return $subscription->refresh();
        }

        if ($daysPastDue <= 15) {
            $subscription->forceFill([
                'operational_status' => Subscription::OPERATIONAL_RESTRICTED,
                'restricted_at' => $subscription->restricted_at ?? $now,
            ])->save();

            return $subscription->refresh();
        }

        if ($daysPastDue <= 30) {
            $subscription->forceFill([
                'operational_status' => Subscription::OPERATIONAL_PRESERVATION,
                'preservation_at' => $subscription->preservation_at ?? $now,
            ])->save();

            return $subscription->refresh();
        }

        $subscription->forceFill([
            'status' => 'suspended',
            'billing_status' => Subscription::BILLING_SUSPENDED,
            'operational_status' => Subscription::OPERATIONAL_PRESERVATION,
            'suspended_at' => $subscription->suspended_at ?? $now,
        ])->save();

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $now = CarbonImmutable::now();

        $subscription->forceFill([
            'status' => 'cancelled',
            'billing_status' => Subscription::BILLING_CANCELLED,
            'operational_status' => Subscription::OPERATIONAL_PRESERVATION,
            'cancelled_at' => $subscription->cancelled_at ?? $now,
        ])->save();

        return $subscription->refresh();
    }
}
