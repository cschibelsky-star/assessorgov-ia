<?php

namespace Tests\Feature\Radar;

use App\Models\Opportunity;
use App\Services\Radar\PncpContractingIngestor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PncpTaiacuAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const CONTROL = '44544690000115-1-000136/2026';

    public function test_captured_official_publication_is_mapped_and_ingested_idempotently(): void
    {
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/pncp/taiacu-publicacao.json')), true, flags: JSON_THROW_ON_ERROR);
        Http::preventStrayRequests();
        Http::fake(['pncp.gov.br/api/consulta/v1/contratacoes/publicacao*' => Http::response($payload)]);

        $this->assertTwoIngestions($payload['data'][0]);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request['dataInicial'] === '20261002'
            && $request['dataFinal'] === '20261002'
            && (int) $request['codigoModalidadeContratacao'] === 6
            && $request['cnpj'] === '44544690000115'
            && (int) $request['pagina'] === 1);
    }

    public function test_live_official_publication_is_found_and_ingested_idempotently(): void
    {
        if (getenv('PNCP_LIVE_ACCEPTANCE') !== '1') {
            $this->markTestSkipped('Opt-in read-only PNCP acceptance: PNCP_LIVE_ACCEPTANCE=1.');
        }

        $official = Http::acceptJson()->timeout(30)->get(
            'https://pncp.gov.br/api/consulta/v1/orgaos/44544690000115/compras/2026/136',
        )->throw()->json();

        $this->assertSame(self::CONTROL, $official['numeroControlePNCP']);
        $this->assertTwoIngestions($official);
    }

    private function assertTwoIngestions(array $official): void
    {
        config()->set('services.pncp.base_url', 'https://pncp.gov.br/api/consulta');
        $ingestor = app(PncpContractingIngestor::class);
        $date = CarbonImmutable::parse('2026-10-02');
        $filters = ['cnpj' => '44544690000115', 'tamanhoPagina' => 50];

        $first = $ingestor->ingest($date, $date, 6, $filters, maxPages: 1);
        $this->assertSame(1, $first['received']);
        $this->assertSame(1, $first['upserted']);
        $this->assertSame([], $first['errors']);
        $this->assertDatabaseCount('opportunities', 1);

        $opportunity = Opportunity::query()->sole();
        $id = $opportunity->id;
        $this->assertSame(self::CONTROL, $opportunity->external_id);
        $this->assertSame('pncp:contratacoes', $opportunity->source_name);
        $this->assertSame('licitacao', $opportunity->channel);
        $this->assertSame('review', $opportunity->status);
        $this->assertSame('PNCP 21/2026', $opportunity->title);
        $this->assertSame($official['objetoCompra'], $opportunity->summary);
        $this->assertSame($official['orgaoEntidade']['razaoSocial'], $opportunity->organization);
        $this->assertSame('Municipal/SP', $opportunity->jurisdiction);
        $this->assertSame('SP', $opportunity->state);
        $this->assertSame(['Taiaçu'], $opportunity->municipalities);
        $this->assertSame('84882.67', $opportunity->estimated_value);
        $this->assertEquals($official['valorTotalEstimado'], (float) $opportunity->estimated_value);
        $this->assertSame($official['linkSistemaOrigem'], $opportunity->source_url);
        foreach (['opens_at' => 'dataAberturaProposta', 'closes_at' => 'dataEncerramentoProposta', 'event_at' => 'dataPublicacaoPncp'] as $field => $source) {
            $this->assertSame($official[$source], $opportunity->$field->format('Y-m-d\TH:i:s'));
        }
        $this->assertSame('2026-10-16 08:59:00', $opportunity->closes_at->format('Y-m-d H:i:s'));
        $this->assertSame('713', $opportunity->metadata['numero_processo']);
        $this->assertSame($official['processo'], $opportunity->metadata['numero_processo']);
        $this->assertSame(6, $opportunity->metadata['modalidade_id']);
        $this->assertSame($official['modalidadeNome'], $opportunity->metadata['modalidade_nome']);
        $this->assertSame('44544690000115', $opportunity->metadata['orgao_cnpj']);

        $second = $ingestor->ingest($date, $date, 6, $filters, maxPages: 1);
        $this->assertSame(1, $second['upserted']);
        $this->assertSame([], $second['errors']);
        $this->assertDatabaseCount('opportunities', 1);
        $this->assertSame($id, Opportunity::query()->sole()->id);
        $this->assertSame($opportunity->summary, Opportunity::query()->sole()->summary);
        $this->assertSame($opportunity->metadata['numero_processo'], Opportunity::query()->sole()->metadata['numero_processo']);
    }
}
