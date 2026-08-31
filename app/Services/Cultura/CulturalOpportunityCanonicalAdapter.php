<?php

namespace App\Services\Cultura;

use App\Contracts\Radar\OpportunitySourceAdapter;
use App\Models\CulturalOpportunity;
use App\Models\Opportunity;
use InvalidArgumentException;

class CulturalOpportunityCanonicalAdapter implements OpportunitySourceAdapter
{
    public function toCanonical(mixed $source): array
    {
        if (! $source instanceof CulturalOpportunity) {
            throw new InvalidArgumentException('CulturalOpportunityCanonicalAdapter expects a CulturalOpportunity instance.');
        }

        return [
            'external_id' => $source->external_id ?: 'cultura-'.$source->getKey(),
            'title' => $source->title,
            'summary' => $source->summary,
            'source_url' => $source->source_url,
            'source_type' => $source->source_type ?: 'official',
            'organization' => $source->organization,
            'jurisdiction' => $source->state ? 'Estado de '.$source->state : null,
            'state' => $source->state,
            'municipalities' => $source->municipalities,
            'estimated_value' => $source->funding_max ?? $source->funding_min,
            'opens_at' => $source->opens_at,
            'closes_at' => $source->closes_at,
            'requirements' => $source->eligibility_rules,
            'required_documents' => $source->required_documents,
            'metadata' => array_merge($source->metadata ?? [], [
                'vertical' => 'cultura',
                'cultural_opportunity_id' => $source->getKey(),
                'cultural_areas' => $source->cultural_areas,
                'eligible_legal_profiles' => $source->eligible_legal_profiles,
                'funding_min' => $source->funding_min,
                'funding_max' => $source->funding_max,
                'opportunity_type' => $source->opportunity_type,
            ]),
            'status' => $source->status,
            'source_checked_at' => $source->source_checked_at,
            '_channel' => Opportunity::CHANNEL_FOMENTO,
            '_source_name' => 'cultura:'.$source->source_name,
        ];
    }
}
