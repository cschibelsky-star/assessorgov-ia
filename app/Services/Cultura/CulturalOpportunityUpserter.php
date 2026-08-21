<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use App\Models\CulturalSource;

class CulturalOpportunityUpserter
{
    public function __construct(
        private readonly CulturalOpportunityNormalizer $normalizer,
    ) {
    }

    public function upsert(CulturalSource $source, array $raw): CulturalOpportunity
    {
        $data = $this->normalizer->normalize($source, $raw);

        return CulturalOpportunity::query()->updateOrCreate(
            [
                'source_name' => $data['source_name'],
                'external_id' => $data['external_id'],
            ],
            $data,
        );
    }
}
