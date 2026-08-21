<?php

namespace App\Services\Cultura\Parsers;

use App\Models\CulturalSource;

interface CulturalSourceParser
{
    public function supports(CulturalSource $source): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(CulturalSource $source, string $body): array;
}
