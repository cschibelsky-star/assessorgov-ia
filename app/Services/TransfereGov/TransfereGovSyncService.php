<?php

namespace App\Services\TransfereGov;

use InvalidArgumentException;

class TransfereGovSyncService
{
    public function __construct(
        private readonly TransfereGovClient $client,
        private readonly TransfereGovNormalizer $normalizer,
        private readonly TransfereGovRepository $repository,
    ) {}

    public function sync(
        string $module,
        string $endpoint,
        array $filters = [],
        int $maxPages = 1,
        ?callable $mapper = null,
    ): array {
        if ($maxPages < 1) {
            throw new InvalidArgumentException('maxPages must be at least 1.');
        }

        $processed = 0;
        $pageNumber = 1;
        $reportedTotal = null;
        $reportedPages = null;

        while ($pageNumber <= $maxPages) {
            $page = $this->client->page($module, $endpoint, $filters, $pageNumber);
            $reportedTotal = (int) $page['total_items'];
            $reportedPages = (int) $page['total_pages'];

            foreach ($page['data'] as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $normalized = $this->normalizer->normalize($module, $record);
                $mapped = $mapper ? (array) $mapper($record) : [];
                $this->repository->persist($normalized, $mapped);
                $processed++;
            }

            if ($pageNumber >= $reportedPages || $page['data'] === []) {
                break;
            }

            $pageNumber++;
        }

        return [
            'module' => $module,
            'endpoint' => $endpoint,
            'processed' => $processed,
            'pages_processed' => $pageNumber,
            'reported_total_items' => $reportedTotal,
            'reported_total_pages' => $reportedPages,
            'truncated' => $reportedPages !== null && $pageNumber < $reportedPages,
        ];
    }
}
