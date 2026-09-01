<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CulturalOpportunityPublisher
{
    public function __construct(
        private readonly SyncCulturalOpportunityToCanonical $canonicalSync,
    ) {
    }

    public function pendingReview(int $limit = 100): Collection
    {
        return CulturalOpportunity::query()
            ->where('state', 'SP')
            ->where('status', 'review')
            ->orderBy('closes_at')
            ->limit($limit)
            ->get();
    }

    public function publish(CulturalOpportunity $opportunity): CulturalOpportunity
    {
        return DB::transaction(function () use ($opportunity): CulturalOpportunity {
            $opportunity->forceFill([
                'status' => 'active',
                'metadata' => array_merge($opportunity->metadata ?? [], [
                    'published_at' => now()->toIso8601String(),
                    'publication_gate' => 'human_or_validated_source',
                ]),
            ])->save();

            $opportunity = $opportunity->refresh();
            $canonical = $this->canonicalSync->sync($opportunity);

            $opportunity->forceFill([
                'metadata' => array_merge($opportunity->metadata ?? [], [
                    'canonical_opportunity_id' => $canonical->getKey(),
                    'canonical_synced_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return $opportunity->refresh();
        });
    }

    public function reject(CulturalOpportunity $opportunity, string $reason): CulturalOpportunity
    {
        $opportunity->forceFill([
            'status' => 'rejected',
            'metadata' => array_merge($opportunity->metadata ?? [], [
                'rejected_at' => now()->toIso8601String(),
                'rejection_reason' => $reason,
            ]),
        ])->save();

        return $opportunity->refresh();
    }
}
