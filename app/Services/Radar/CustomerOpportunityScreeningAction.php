<?php

namespace App\Services\Radar;

use App\Models\CustomerOpportunity;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Authenticated application entry point for an explicit item pre-screening action.
 * No background execution, notifications, or proposal submission.
 */
final class CustomerOpportunityScreeningAction
{
    public function __construct(private readonly CustomerOpportunityScreeningPersister $persister) {}

    public function execute(
        User $actor,
        string $customerOpportunityId,
        array $item,
        array $profile,
        DateTimeImmutable $at,
    ): CustomerOpportunity {
        // Reload the actor: unsaved, disabled or stale tenant claims are denied.
        $persistedActor = $actor->exists ? User::query()->find($actor->getKey()) : null;
        if (! $persistedActor || $persistedActor->status !== 'active'
            || $persistedActor->customer_id !== $actor->customer_id
            || ! $persistedActor->can('radar.screen')) {
            throw new AuthorizationException('An active actor with radar.screen is required.');
        }

        $customerId = $persistedActor->customer?->getKey();

        if (! is_string($customerId) || $customerId === ''
            || ($profile['customer_id'] ?? null) !== $customerId) {
            throw new AuthorizationException('A matching authenticated customer is required.');
        }

        return $this->persister->persist(
            $customerId,
            $customerOpportunityId,
            $item,
            $profile,
            $at,
        );
    }
}
