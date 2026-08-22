<?php

namespace App\Services\Cultura;

use App\Models\User;

class CulturaPlanResolver
{
    public function resolve(?User $user): array
    {
        if (! $user || ! $user->customer) {
            return $this->payload('gratuito');
        }

        $subscription = $user->customer
            ->subscriptions()
            ->with('plan')
            ->latest('activated_at')
            ->get()
            ->first(fn ($subscription) => $subscription->isActive() && $subscription->plan?->is_active);

        $slug = $subscription?->plan?->slug ?? 'gratuito';

        if (! array_key_exists($slug, config('assessorgov_cultura.plans', []))) {
            $slug = 'gratuito';
        }

        return $this->payload($slug, $subscription);
    }

    private function payload(string $slug, $subscription = null): array
    {
        return [
            'slug' => $slug,
            'limits' => config('assessorgov_cultura.plans.' . $slug, []),
            'subscription' => $subscription,
        ];
    }
}
