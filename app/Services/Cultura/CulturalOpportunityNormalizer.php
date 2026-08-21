<?php

namespace App\Services\Cultura;

use App\Models\CulturalSource;
use Illuminate\Support\Str;

class CulturalOpportunityNormalizer
{
    public function normalize(CulturalSource $source, array $raw): array
    {
        $title = trim((string) ($raw['title'] ?? ''));
        $url = trim((string) ($raw['source_url'] ?? $source->url));
        $externalId = trim((string) ($raw['external_id'] ?? ''));

        if ($externalId === '') {
            $externalId = hash('sha256', implode('|', [
                $source->key,
                Str::lower(Str::squish($title)),
                Str::lower($url),
            ]));
        }

        return [
            'source_name' => $source->key,
            'source_type' => 'official',
            'source_url' => $url,
            'external_id' => $externalId,
            'title' => $title,
            'summary' => $raw['summary'] ?? null,
            'organization' => $raw['organization'] ?? $source->owner,
            'opportunity_type' => $raw['opportunity_type'] ?? null,
            'state' => 'SP',
            'municipalities' => $raw['municipalities'] ?? ($source->municipality ? [$source->municipality] : null),
            'cultural_areas' => $raw['cultural_areas'] ?? null,
            'eligible_legal_profiles' => $raw['eligible_legal_profiles'] ?? null,
            'funding_min' => $raw['funding_min'] ?? null,
            'funding_max' => $raw['funding_max'] ?? null,
            'opens_at' => $raw['opens_at'] ?? null,
            'closes_at' => $raw['closes_at'] ?? null,
            'eligibility_rules' => $raw['eligibility_rules'] ?? null,
            'required_documents' => $raw['required_documents'] ?? null,
            'metadata' => array_merge($raw['metadata'] ?? [], [
                'source_key' => $source->key,
                'normalized_at' => now()->toIso8601String(),
            ]),
            'status' => $raw['status'] ?? 'review',
            'source_checked_at' => now(),
        ];
    }
}
