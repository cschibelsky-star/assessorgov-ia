<?php

namespace App\Services\Radar;

use App\Models\Opportunity;

class OpportunityUpserter
{
    public function upsert(array $normalized): Opportunity
    {
        $opportunity = Opportunity::query()->updateOrCreate(
            [
                'source_name' => $normalized['source_name'],
                'external_id' => $normalized['external_id'],
            ],
            $normalized,
        );

        return $opportunity->refresh();
    }
}
