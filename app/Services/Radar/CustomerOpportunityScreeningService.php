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
        // A client/item payload cannot establish the linked opportunity's validity.
        $opportunity = $record->exists ? $record->opportunity : null;
        $opportunityGaps = [];
        if (! $opportunity || $opportunity->status !== 'active') {
            $opportunityGaps[] = 'opportunity_status';
        }
        if (! $opportunity?->closes_at || $opportunity->closes_at <= $at) {
            $opportunityGaps[] = 'opportunity_deadline';
        }
        if (! $opportunity?->source_checked_at || $opportunity->source_checked_at > $at
            || $opportunity->source_type !== 'official'
            || ! filter_var($opportunity->source_url, FILTER_VALIDATE_URL)) {
            $opportunityGaps[] = 'opportunity_source_confirmation';
        }
        if (($item['opportunity_id'] ?? null) !== (string) $record->opportunity_id) {
            $opportunityGaps[] = 'item_opportunity_scope';
        }
        if ($opportunityGaps !== []) {
            $result['gaps'] = array_values(array_unique(array_merge($result['gaps'], $opportunityGaps)));
            $result['match_reasons'][] = 'Oportunidade ou vínculo do item não confirmado; análise operacional bloqueada.';
            if ($result['eligibility'] !== 'blocked') {
                $result['eligibility'] = 'pending_evidence';
            }
            if ($result['classification'] === 'compatible_for_analysis') {
                $result['classification'] = 'pending_evidence';
            }
        }
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
