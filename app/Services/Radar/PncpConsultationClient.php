<?php

namespace App\Services\Radar;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PncpConsultationClient
{
    public function fetchContractingsPage(
        DateTimeInterface $from,
        DateTimeInterface $to,
        int $modalityCode,
        int $page = 1,
        array $filters = [],
    ): array {
        if ($modalityCode < 1) {
            throw new RuntimeException('PNCP modality code must be a positive integer.');
        }

        if ($page < 1) {
            throw new RuntimeException('PNCP page must be greater than zero.');
        }

        $query = array_filter(array_merge([
            'dataInicial' => $from->format('Ymd'),
            'dataFinal' => $to->format('Ymd'),
            'codigoModalidadeContratacao' => $modalityCode,
            'pagina' => $page,
        ], $filters), static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->request()
            ->get('/v1/contratacoes/publicacao', $query)
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('PNCP returned an invalid JSON payload.');
        }

        if (isset($payload['data']) && ! is_array($payload['data'])) {
            throw new RuntimeException('PNCP payload field [data] is invalid.');
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pncp.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.pncp.timeout', 20))
            ->connectTimeout((int) config('services.pncp.connect_timeout', 5))
            ->retry(
                (int) config('services.pncp.retry_attempts', 3),
                (int) config('services.pncp.retry_sleep_ms', 750),
                throw: false,
            );
    }
}
