<?php

namespace App\Services\Cultura;

use App\Models\CulturalOpportunity;
use App\Models\CulturalSource;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CulturalOpportunityIngestor
{
    public function __construct(private readonly CulturalOpportunityNormalizer $normalizer)
    {
    }

    public function ingest(CulturalSource $source, array $items): array
    {
        if (!$source->enabled) {
            throw new InvalidArgumentException("Cultural source {$source->key} is disabled.");
        }

        $stats = ['received' => count($items), 'created' => 0, 'updated' => 0, 'rejected' => 0];

        DB::transaction(function () use ($source, $items, &$stats): void {
            foreach ($items as $raw) {
                $normalized = $this->normalizer->normalize($source, (array) $raw);

                if ($normalized['title'] === '' || $normalized['source_url'] === '') {
                    $stats['rejected']++;
                    continue;
                }

                $opportunity = CulturalOpportunity::query()->firstOrNew([
                    'source_name' => $normalized['source_name'],
                    'external_id' => $normalized['external_id'],
                ]);

                $wasNew = !$opportunity->exists;
                $opportunity->fill($normalized);
                $opportunity->save();

                $stats[$wasNew ? 'created' : 'updated']++;
            }

            $source->forceFill([
                'last_checked_at' => now(),
                'last_success_at' => now(),
                'last_status' => 'ok',
                'last_error' => null,
            ])->save();
        });

        return $stats;
    }

    public function fail(CulturalSource $source, string $message): void
    {
        $source->forceFill([
            'last_checked_at' => now(),
            'last_status' => 'error',
            'last_error' => mb_substr($message, 0, 4000),
        ])->save();
    }
}
