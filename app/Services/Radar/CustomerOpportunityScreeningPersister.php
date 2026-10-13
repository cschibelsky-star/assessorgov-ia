<?php

namespace App\Services\Radar;

use App\Models\CustomerOpportunity;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Explicit, tenant-scoped persistence of a screening result.
 * Never advances the funnel, submits proposals, or sends notifications.
 */
final class CustomerOpportunityScreeningPersister
{
    public function __construct(private readonly CustomerOpportunityScreeningService $screening) {}

    public function persist(
        string $customerId,
        string $customerOpportunityId,
        array $item,
        array $profile,
        DateTimeImmutable $at,
    ): CustomerOpportunity {
        if ($customerId === '' || ($profile['customer_id'] ?? null) !== $customerId) {
            throw new InvalidArgumentException('Customer scope mismatch.');
        }

        return DB::transaction(function () use ($customerId, $customerOpportunityId, $item, $profile, $at) {
            $record = CustomerOpportunity::query()
                ->whereKey($customerOpportunityId)
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $prepared = $this->screening->prepare($record, $item, $profile, $at);
            $record->fill($prepared);
            $record->save();

            return $record->refresh();
        });
    }
}
