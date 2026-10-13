<?php

namespace App\Services\Radar;

use App\Models\CustomerOpportunity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Bridges reviewed item screening to a tenant-scoped funnel record.
 * Does not send notifications, approve proposals or change funnel stages.
 */
final class CustomerOpportunityScreeningService
{
    public function __construct(private readonly ItemEligibilityClassifier $classifier) {}

    public function prepare(
        CustomerOpportunity $record,
        array $item,
        array $profile,
        DateTimeImmutable $at,
    ): array {
        $recordCustomer = (string) $record->customer_id;
        $profileCustomer = $profile['customer_id'] ?? null;

        if ($recordCustomer === '' || ! is_string($profileCustomer)
            || $profileCustomer !== $recordCustomer) {
            throw new InvalidArgumentException('Screening requires a matching customer scope.');
        }

        if (! $record->opportunity_id) {
            throw new InvalidArgumentException('Screening requires a linked opportunity.');
        }

        $result = $this->classifier->classify($item, $profile, $at);
        $metadata = is_array($record->metadata) ? $record->metadata : [];

        $metadata['item_screening'] = [
            'item_id' => $item['id'] ?? null,
            'commercial_fit' => $result['commercial_fit'],
            'eligibility' => $result['eligibility'],
            'classification' => $result['classification'],
            'commercial_notification_allowed' => false,
            'proposal_allowed' => false,
            'screened_at' => $at->format(DATE_ATOM),
        ];

        return [
            'match_score' => null,
            'match_reasons' => $result['match_reasons'],
            'gaps' => $result['gaps'],
            'metadata' => $metadata,
        ];
    }
}
