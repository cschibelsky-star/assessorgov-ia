<?php

namespace Tests\Feature\Radar;

use App\Services\Radar\PncpContractingAdapter;
use App\Services\Radar\PncpContractingIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PncpContractingTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_maps_official_record_to_canonical_shape(): void
    {
        $record = [
            'numeroControlePNCP' => 'PNCP-1',
            'objetoCompra' => 'Serviço de tecnologia',
            'numeroCompra' => '10',
            'anoCompra' => '2026',
            'linkSistemaOrigem' => 'https://example.test/processo',
            'valorTotalEstimado' => 1000,
            'orgaoEntidade' => ['razaoSocial' => 'Órgão Teste', 'esferaId' => 'M'],
            'unidadeOrgao' => ['ufSigla' => 'SP', 'municipioNome' => 'Sumaré'],
        ];

        $canonical = app(PncpContractingAdapter::class)->toCanonical($record);

        $this->assertSame('PNCP-1', $canonical['external_id']);
        $this->assertSame('PNCP 10/2026', $canonical['title']);
        $this->assertSame('pncp:contratacoes', $canonical['_source_name']);
    }

    public function test_ingestor_paginates_until_official_payload_reports_last_page(): void
    {
        config()->set('services.pncp.base_url', 'https://pncp.test');
        Http::fakeSequence()
            ->push(['data' => [$this->record('PNCP-1')], 'totalPaginas' => 2], 200)
            ->push(['data' => [$this->record('PNCP-2')], 'totalPaginas' => 2], 200);

        $result = app(PncpContractingIngestor::class)->ingest(
            now()->subDay(),
            now(),
            1,
        );

        $this->assertSame(2, $result['pages']);
        $this->assertSame(2, $result['upserted']);
        $this->assertDatabaseCount('opportunities', 2);
    }

    public function test_client_retries_after_server_error(): void
    {
        config()->set('services.pncp.base_url', 'https://pncp.test');
        config()->set('services.pncp.retry_attempts', 2);
        config()->set('services.pncp.retry_sleep_ms', 0);

        Http::fakeSequence()
            ->push([], 500)
            ->push(['data' => [$this->record('PNCP-RETRY')], 'totalPaginas' => 1], 200);

        $result = app(PncpContractingIngestor::class)->ingest(now()->subDay(), now(), 1);

        $this->assertSame(1, $result['upserted']);
        Http::assertSentCount(2);
    }

    private function record(string $id): array
    {
        return [
            'numeroControlePNCP' => $id,
            'objetoCompra' => 'Serviço de tecnologia',
            'numeroCompra' => '10',
            'anoCompra' => '2026',
            'linkSistemaOrigem' => 'https://example.test/'.$id,
        ];
    }
}
