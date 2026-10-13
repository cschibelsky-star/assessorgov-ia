<?php

namespace App\Services\Radar;

use App\Models\CustomerOpportunity;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Safe application entry point: evidence is loaded server-side from reviewed
 * compliance records, never accepted from a client-provided profile.
 */
final class ReviewedCustomerOpportunityScreeningAction
{
    public function __construct(
        private readonly ReviewedEvidenceProfileProvider $profiles,
        private readonly CustomerOpportunityScreeningPersister $persister,
    ) {}

    public function execute(
        User $actor,
        string $customerOpportunityId,
        array $reviewedItem,
        DateTimeImmutable $at,
    ): CustomerOpportunity {
        // Reload the actor: unsaved, disabled or stale tenant claims are denied.
        $persistedActor = $actor->exists ? User::query()->find($actor->getKey()) : null;
        if (! $persistedActor || $persistedActor->status !== 'active'
            || $persistedActor->customer_id !== $actor->customer_id
            || ! $persistedActor->can('radar.screen')) {
            throw new AuthorizationException('An active actor with radar.screen is required.');
        }

        $customerId = $persistedActor->customer_id;

        if (! is_string($customerId) || trim($customerId) === '') {
            throw new AuthorizationException('Authenticated customer required.');
        }

        // No browser-supplied evidence, validity or activity claims are used.
        $profile = $this->profiles->forCustomer($customerId);

        return $this->persister->persist(
            $customerId,
            $customerOpportunityId,
            $reviewedItem,
            $profile,
            $at,
        );
    }
}
