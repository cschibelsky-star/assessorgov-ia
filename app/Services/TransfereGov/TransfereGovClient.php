<?php

namespace App\Services\TransfereGov;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TransfereGovClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('transferegov.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('transferegov.timeout', 20))
            ->retry(2, 500);
    }

    public function get(string $module, string $endpoint = '', array $query = []): array
    {
        $modules = (array) config('transferegov.modules', []);

        if (! isset($modules[$module])) {
            throw new InvalidArgumentException("Unsupported Transferegov module: {$module}");
        }

        $path = rtrim((string) $modules[$module], '/');
        $endpoint = trim($endpoint, '/');

        if ($endpoint !== '') {
            $path .= '/'.$endpoint;
        }

        return $this->http()->get($path, $query)->throw()->json();
    }

    public function page(string $module, string $endpoint, array $filters = [], int $page = 1, ?int $pageSize = null): array
    {
        $pageSize ??= (int) config('transferegov.page_size', 200);

        if ($page < 1 || $pageSize < 1 || $pageSize > 200) {
            throw new InvalidArgumentException('Transferegov pagination requires page >= 1 and page size between 1 and 200.');
        }

        $payload = $this->get($module, $endpoint, array_merge($filters, [
            'pagina' => $page,
            'tamanho_da_pagina' => $pageSize,
        ]));

        foreach (['data', 'total_pages', 'total_items', 'page_number', 'page_size'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new RuntimeException("Unexpected Transferegov response: missing {$field}.");
            }
        }

        if (! is_array($payload['data'])) {
            throw new RuntimeException('Unexpected Transferegov response: data must be an array.');
        }

        return $payload;
    }

    public function parcerias(string $endpoint = '', array $query = []): array
    {
        return $this->get('parcerias', $endpoint, $query);
    }

    public function transferenciasEspeciais(string $endpoint = '', array $query = []): array
    {
        return $this->get('especiais', $endpoint, $query);
    }

    public function fundoAFundo(string $endpoint = '', array $query = []): array
    {
        return $this->get('fundoafundo', $endpoint, $query);
    }
}
