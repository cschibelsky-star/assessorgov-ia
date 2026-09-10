<?php

namespace App\Services\TransfereGov;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class TransfereGovClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('transferegov.base_url'), '/'))
            ->acceptJson()
            ->asJson()
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

    public function parcerias(string $endpoint = '', array $query = []): array
    {
        return $this->get('parcerias', $endpoint, $query);
    }

    public function transferenciasEspeciais(string $endpoint = '', array $query = []): array
    {
        return $this->get('especiais', $endpoint, $query);
    }
}
