<?php

namespace App\Services\Cultura;

use App\Models\CulturalSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CulturalSourceIngestor
{
    public function ingest(CulturalSource $source): array
    {
        $source->forceFill([
            'last_checked_at' => now(),
            'last_status' => 'running',
            'last_error' => null,
        ])->save();

        try {
            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'AssessorGov-Cultura/0.1 (+official-opportunity-monitor)',
                    'Accept' => 'text/html,application/xhtml+xml,application/json',
                ])
                ->get($source->url);

            if (! $response->successful()) {
                throw new RuntimeException('HTTP '.$response->status());
            }

            $contentType = (string) $response->header('Content-Type');
            $body = $response->body();

            $source->forceFill([
                'last_success_at' => now(),
                'last_status' => 'fetched',
                'last_error' => null,
                'metadata' => array_merge($source->metadata ?? [], [
                    'last_http_status' => $response->status(),
                    'last_content_type' => $contentType,
                    'last_content_sha256' => hash('sha256', $body),
                    'last_content_bytes' => strlen($body),
                ]),
            ])->save();

            return [
                'source' => $source,
                'content_type' => $contentType,
                'body' => $body,
            ];
        } catch (\Throwable $e) {
            $source->forceFill([
                'last_status' => 'error',
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }
}
