<?php

namespace App\Services\Radar;

use DateTimeInterface;
use RuntimeException;

class PncpContractingIngestor
{
    public function __construct(
        private readonly PncpConsultationClient $client,
        private readonly PncpContractingAdapter $adapter,
        private readonly OpportunityNormalizer $normalizer,
        private readonly OpportunityUpserter $upserter,
    ) {
    }

    public function ingest(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $modalityCode,
        array $filters = [],
        int $maxPages = 100,
    ): array {
        if ($maxPages < 1) {
            throw new RuntimeException('PNCP max pages must be greater than zero.');
        }

        $stats = [
            'pages' => 0,
            'received' => 0,
            'upserted' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = $this->client->fetchContractingsPage(
                $from,
                $to,
                $modalityCode,
                $page,
                $filters,
            );

            $records = $payload['data'] ?? [];
            $stats['pages']++;
            $stats['received'] += count($records);

            foreach ($records as $index => $record) {
                try {
                    $raw = $this->adapter->toCanonical($record);
                    $channel = $raw['_channel'];
                    $sourceName = $raw['_source_name'];
                    unset($raw['_channel'], $raw['_source_name']);

                    $normalized = $this->normalizer->normalize($raw, $channel, $sourceName);
                    $this->upserter->upsert($normalized);
                    $stats['upserted']++;
                } catch (\Throwable $exception) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'page' => $page,
                        'index' => $index,
                        'external_id' => is_array($record) ? ($record['numeroControlePNCP'] ?? null) : null,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if (! $this->hasNextPage($payload, $page, count($records))) {
                break;
            }
        }

        return $stats;
    }

    private function hasNextPage(array $payload, int $currentPage, int $recordCount): bool
    {
        if (isset($payload['totalPaginas'])) {
            return $currentPage < (int) $payload['totalPaginas'];
        }

        if (array_key_exists('paginasRestantes', $payload)) {
            return (int) $payload['paginasRestantes'] > 0;
        }

        if (array_key_exists('empty', $payload)) {
            return ! (bool) $payload['empty'] && $recordCount > 0;
        }

        return $recordCount > 0;
    }
}
