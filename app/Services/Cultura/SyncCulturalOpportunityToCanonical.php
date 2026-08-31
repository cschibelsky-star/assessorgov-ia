<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use App\Models\Opportunity;
use App\Services\Radar\OpportunityNormalizer;
use App\Services\Radar\OpportunityUpserter;

class SyncCulturalOpportunityToCanonical
{
    public function __construct(
        private readonly CulturalOpportunityCanonicalAdapter $adapter,
        private readonly OpportunityNormalizer $normalizer,
        private readonly OpportunityUpserter $upserter,
    ) {
    }

    public function sync(CulturalOpportunity $culturalOpportunity): Opportunity
    {
        $raw = $this->adapter->toCanonical($culturalOpportunity);
        $channel = $raw['_channel'];
        $sourceName = $raw['_source_name'];

        unset($raw['_channel'], $raw['_source_name']);

        $normalized = $this->normalizer->normalize($raw, $channel, $sourceName);

        return $this->upserter->upsert($normalized);
    }
}
