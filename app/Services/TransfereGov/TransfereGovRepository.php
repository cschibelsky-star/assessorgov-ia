<?php

namespace App\Services\TransfereGov;

use App\Models\GovernmentOpportunity;

class TransfereGovRepository
{
    public function persist(array $normalized, array $mapped = []): GovernmentOpportunity
    {
        $identity = [
            'source' => $normalized['source'],
            'source_module' => $normalized['source_module'],
            'external_id' => $normalized['external_id'],
        ];

        $attributes = array_merge($mapped, [
            'source_url' => $normalized['source_url'],
            'fetched_at' => $normalized['fetched_at'],
            'raw_payload_hash' => $normalized['raw_payload_hash'],
            'raw_payload' => $normalized['raw_payload'],
        ]);

        if ($normalized['external_id'] === null) {
            return GovernmentOpportunity::updateOrCreate(
                [
                    'source' => $normalized['source'],
                    'source_module' => $normalized['source_module'],
                    'raw_payload_hash' => $normalized['raw_payload_hash'],
                ],
                $attributes
            );
        }

        return GovernmentOpportunity::updateOrCreate($identity, $attributes);
    }
}
