<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivateSubscription
{
    public function handle(Subscription $subscription, array $metadata = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $metadata): Subscription {
            $subscription->refresh();

            if ($subscription->status === 'cancelled') {
                throw new RuntimeException('A cancelled subscription cannot be activated.');
            }

            $subscription->forceFill([
                'status' => 'active',
                'activated_at' => $subscription->activated_at ?? now(),
                'cancelled_at' => null,
                'metadata' => array_merge($subscription->metadata ?? [], $metadata),
            ])->save();

            $subscription->customer()->update(['status' => 'active']);
            $subscription->customer->users()->update(['status' => 'active']);

            return $subscription->fresh(['customer', 'plan']);
        });
    }
}
