<?php

namespace App\Services\Radar;

use App\Models\Opportunity;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpportunityNormalizer
{
    /**
     * @var array<int, string>
     */
    private const CHANNELS = [
        Opportunity::CHANNEL_LICITACAO,
        Opportunity::CHANNEL_IRP,
        Opportunity::CHANNEL_SICX,
        Opportunity::CHANNEL_REMANESCENTE,
        Opportunity::CHANNEL_FOMENTO,
    ];

    public function normalize(array $raw, string $channel, string $sourceName): array
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException("Unsupported opportunity channel [{$channel}].");
        }

        $sourceName = trim($sourceName);
        $title = trim((string) ($raw['title'] ?? ''));
        $sourceUrl = trim((string) ($raw['source_url'] ?? ''));

        if ($sourceName === '') {
            throw new InvalidArgumentException('Opportunity source name is required.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('Opportunity title is required.');
        }

        if ($sourceUrl === '') {
            throw new InvalidArgumentException('Opportunity source URL is required.');
        }

        $externalId = trim((string) ($raw['external_id'] ?? ''));

        if ($externalId === '') {
            $externalId = hash('sha256', implode('|', [
                $channel,
                Str::lower($sourceName),
                Str::lower(Str::squish($title)),
                Str::lower($sourceUrl),
            ]));
        }

        $metadata = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];

        return [
            'channel' => $channel,
            'source_name' => $sourceName,
            'source_type' => $raw['source_type'] ?? 'official',
            'source_url' => $sourceUrl,
            'external_id' => $externalId,
            'title' => $title,
            'summary' => $raw['summary'] ?? null,
            'organization' => $raw['organization'] ?? null,
            'jurisdiction' => $raw['jurisdiction'] ?? null,
            'state' => $raw['state'] ?? null,
            'municipalities' => $raw['municipalities'] ?? null,
            'estimated_value' => $raw['estimated_value'] ?? null,
            'opens_at' => $raw['opens_at'] ?? null,
            'closes_at' => $raw['closes_at'] ?? null,
            'event_at' => $raw['event_at'] ?? null,
            'requirements' => $raw['requirements'] ?? null,
            'required_documents' => $raw['required_documents'] ?? null,
            'metadata' => array_merge($metadata, [
                'normalized_at' => now()->toIso8601String(),
                'canonical_channel' => $channel,
            ]),
            'status' => $raw['status'] ?? 'review',
            'source_checked_at' => $raw['source_checked_at'] ?? now(),
        ];
    }
}
