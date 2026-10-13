<?php

namespace App\Services\Radar;

use App\Models\GovComplianceItem;

/**
 * Reads reviewed compliance evidence from the database, never from browser claims.
 * Existing compliance reviews do not establish legal eligibility for a bid.
 */
final class ReviewedEvidenceProfileProvider
{
    public function forCustomer(string $customerId, array $activities = []): array
    {
        $profile = [
            'customer_id' => $customerId,
            'activities' => $activities,
            'activities_complete' => false,
            'evidence' => [],
        ];

        if ($customerId === '') {
            return $profile;
        }

        $items = GovComplianceItem::query()
            ->with('latestEvidence')
            ->where('customer_id', $customerId)
            ->where('status', 'conformant')
            ->whereNotNull('reviewed_by_user_id')
            ->whereNotNull('reviewed_at')
            ->get();

        foreach ($items as $item) {
            $evidence = $item->latestEvidence;
            if (! $evidence || ! $evidence->uploaded_at
                || $evidence->uploaded_at->greaterThan($item->reviewed_at)
                || ! is_string($evidence->sha256)
                || trim($evidence->sha256) === '') {
                continue;
            }

            // The compliance review has no per-document validity policy.
            // Therefore evidence remains pending for bid eligibility until
            // an independently reviewed expiry/no-expiry rule is available.
            $profile['evidence'][$item->item_key] = [
                'customer_id' => $customerId,
                'status' => 'pending',
                'reference' => $evidence->sha256,
                'valid_until' => null,
            ];
        }

        return $profile;
    }
}
