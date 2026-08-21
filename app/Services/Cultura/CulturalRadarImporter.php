<?php

namespace App\Services\Cultura;

use App\Models\CulturalSource;
use App\Services\Cultura\Parsers\CulturalSourceParser;
use App\Services\Cultura\Parsers\OfficialHtmlOpportunityParser;
use RuntimeException;

class CulturalRadarImporter
{
    /** @var array<int, CulturalSourceParser> */
    private array $parsers;

    public function __construct(
        private readonly CulturalSourceIngestor $ingestor,
        private readonly CulturalOpportunityUpserter $upserter,
        OfficialHtmlOpportunityParser $officialHtmlParser,
    ) {
        $this->parsers = [$officialHtmlParser];
    }

    public function import(CulturalSource $source): array
    {
        $payload = $this->ingestor->ingest($source);
        $parser = $this->parserFor($source);
        $rawItems = $parser->parse($source, $payload['body']);
        $created = 0;
        $updated = 0;

        foreach ($rawItems as $raw) {
            $opportunity = $this->upserter->upsert($source, $raw);
            $opportunity->wasRecentlyCreated ? $created++ : $updated++;
        }

        $source->forceFill([
            'last_status' => 'parsed',
            'metadata' => array_merge($source->metadata ?? [], [
                'last_discovered_count' => count($rawItems),
                'last_created_count' => $created,
                'last_updated_count' => $updated,
                'last_parser' => $parser::class,
                'last_parsed_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return [
            'source' => $source->key,
            'discovered' => count($rawItems),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function parserFor(CulturalSource $source): CulturalSourceParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($source)) {
                return $parser;
            }
        }

        throw new RuntimeException('No parser available for cultural source '.$source->key);
    }
}
