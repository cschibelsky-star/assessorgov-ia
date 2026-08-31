<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use App\Models\Opportunity;

class CulturalOpportunityCanonicalAdapter
{
    public function toCanonical(CulturalOpportunity $opportunity): array
    {
        return [
            'external_id' => $opportunity->external_id ?: 'cultura-'.$opportunity->getKey(),
            'title' => $opportunity->title,
            'summary' => $opportunity->summary,
            'source_url' => $opportunity->source_url,
            'source_type' => $opportunity->source_type ?: 'official',
            'organization' => $opportunity->organization,
            'jurisdiction' => $opportunity->state ? 'Estado de '.$opportunity->state : null,
            'state' => $opportunity->state,
            'municipalities' => $opportunity->municipalities,
            'estimated_value' => $opportunity->funding_max ?? $opportunity->funding_min,
            'opens_at' => $opportunity->opens_at,
            'closes_at' => $opportunity->closes_at,
            'requirements' => $opportunity->eligibility_rules,
            'required_documents' => $opportunity->required_documents,
            'metadata' => array_merge($opportunity->metadata ?? [], [
                'vertical' => 'cultura',
                'cultural_opportunity_id' => $opportunity->getKey(),
                'cultural_areas' => $opportunity->cultural_areas,
                'eligible_legal_profiles' => $opportunity->eligible_legal_profiles,
                'funding_min' => $opportunity->funding_min,
                'funding_max' => $opportunity->funding_max,
                'opportunity_type' => $opportunity->opportunity_type,
            ]),
            'status' => $opportunity->status,
            'source_checked_at' => $opportunity->source_checked_at,
            '_channel' => Opportunity::CHANNEL_FOMENTO,
            '_source_name' => 'cultura:'.$opportunity->source_name,
        ];
    }
}
