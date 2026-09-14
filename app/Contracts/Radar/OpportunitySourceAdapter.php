<?php

namespace App\Contracts\Radar;

interface OpportunitySourceAdapter
{
    /**
     * Convert a source-specific record into the canonical Radar payload.
     */
    public function toCanonical(mixed $source): array;
}
