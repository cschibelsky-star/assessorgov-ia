<?php

namespace Tests\Feature\TransfereGov;

use App\Models\GovernmentOpportunity;
use App\Services\TransfereGov\TransfereGovClient;
use App\Services\TransfereGov\TransfereGovNormalizer;
use App\Services\TransfereGov\TransfereGovRepository;
use App\Services\TransfereGov\TransfereGovSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransfereGovIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_validates_official_paginated_envelope(): void
    {
        config()->set('transferegov.base_url', 'https://api-publica.transferegov.test');
        config()->set('transferegov.modules.parcerias', '/parcerias');
        config()->set('transferegov.page_size', 200);

        Http::fake([
            'https://api-publica.transferegov.test/parcerias/proposta*' => Http::response([
                'data' => [['id' => 10, 'nome' => 'Proposta teste']],
                'total_pages' => 1,
                'total_items' => 1,
                'page_number' => 1,
                'page_size' => 200,
            ], 200),
        ]);

        $page = app(TransfereGovClient::class)->page('parcerias', 'proposta', ['sg_uf_recebedor' => 'SP'], 1);

        $this->assertSame(1, $page['total_items']);
        $this->assertSame(1, $page['total_pages']);
        $this->assertSame(10, $page['data'][0]['id']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/parcerias/proposta')
                && $request['sg_uf_recebedor'] === 'SP'
                && (int) $request['pagina'] === 1
                && (int) $request['tamanho_da_pagina'] === 200;
        });
    }

    public function test_sync_is_idempotent_for_same_external_id(): void
    {
        config()->set('transferegov.base_url', 'https://api-publica.transferegov.test');
        config()->set('transferegov.modules.parcerias', '/parcerias');
        config()->set('transferegov.page_size', 200);

        $payload = [
            'data' => [[
                'id' => 123,
                'titulo' => 'Chamamento de teste',
                'sg_uf_recebedor' => 'SP',
            ]],
            'total_pages' => 1,
            'total_items' => 1,
            'page_number' => 1,
            'page_size' => 200,
        ];

        Http::fake([
            'https://api-publica.transferegov.test/parcerias/proposta*' => Http::response($payload, 200),
        ]);

        $service = new TransfereGovSyncService(
            app(TransfereGovClient::class),
            app(TransfereGovNormalizer::class),
            app(TransfereGovRepository::class),
        );

        $mapper = fn (array $record): array => [
            'title' => $record['titulo'] ?? null,
            'state' => $record['sg_uf_recebedor'] ?? null,
        ];

        $first = $service->sync('parcerias', 'proposta', [], 1, $mapper);
        $second = $service->sync('parcerias', 'proposta', [], 1, $mapper);

        $this->assertSame(1, $first['processed']);
        $this->assertSame(1, $second['processed']);
        $this->assertSame(1, GovernmentOpportunity::query()->count());

        $opportunity = GovernmentOpportunity::query()->firstOrFail();
        $this->assertSame('transferegov', $opportunity->source);
        $this->assertSame('parcerias', $opportunity->source_module);
        $this->assertSame('123', $opportunity->external_id);
        $this->assertSame('Chamamento de teste', $opportunity->title);
        $this->assertSame('SP', $opportunity->state);
        $this->assertNotEmpty($opportunity->raw_payload_hash);
    }

    public function test_sync_reports_truncation_when_max_pages_is_reached(): void
    {
        config()->set('transferegov.base_url', 'https://api-publica.transferegov.test');
        config()->set('transferegov.modules.especiais', '/especiais');
        config()->set('transferegov.page_size', 200);

        Http::fake([
            'https://api-publica.transferegov.test/especiais/transferencia*' => Http::response([
                'data' => [['id' => 1]],
                'total_pages' => 5,
                'total_items' => 1000,
                'page_number' => 1,
                'page_size' => 200,
            ], 200),
        ]);

        $service = new TransfereGovSyncService(
            app(TransfereGovClient::class),
            app(TransfereGovNormalizer::class),
            app(TransfereGovRepository::class),
        );

        $result = $service->sync('especiais', 'transferencia', [], 1);

        $this->assertTrue($result['truncated']);
        $this->assertSame(1, $result['pages_processed']);
        $this->assertSame(5, $result['reported_total_pages']);
        $this->assertSame(1000, $result['reported_total_items']);
    }
}
