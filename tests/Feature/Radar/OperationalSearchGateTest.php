<?php

namespace Tests\Feature\Radar;

use App\Models\CulturalSource;
use App\Services\Cultura\CulturalSourceIngestor;
use App\Services\Radar\OperationalSearchGate;
use App\Services\Radar\PncpConsultationClient;
use App\Services\TransfereGov\TransfereGovClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OperationalSearchGateTest extends TestCase
{
    public function test_each_real_source_is_blocked_before_http_or_database_mutation(): void
    {
        Http::fake();
        $this->app['env'] = 'homologation';
        config(['assessorgov_gate.homologation_approved' => false, 'assessorgov_gate.real_search_enabled' => false]);
        $date = new DateTimeImmutable('2026-10-13');
        $source = new CulturalSource(['url' => 'https://synthetic.example.test']);
        foreach ([
            fn () => app(PncpConsultationClient::class)->fetchContractingsPage($date, $date, 1),
            fn () => app(TransfereGovClient::class)->get('parcerias'),
            fn () => app(CulturalSourceIngestor::class)->ingest($source),
        ] as $call) {
            try {
                $call();
                $this->fail('Real-source entry point bypassed the homologation gate.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Real searches blocked', $exception->getMessage());
            }
        }
        $this->assertNull($source->last_checked_at);
        Http::assertNothingSent();
    }

    public function test_one_flag_alone_never_authorizes_search(): void
    {
        $this->app['env'] = 'homologation';
        foreach ([[true, false], [false, true]] as [$approved, $enabled]) {
            config(['assessorgov_gate.homologation_approved' => $approved, 'assessorgov_gate.real_search_enabled' => $enabled]);
            try {
                app(OperationalSearchGate::class)->assertAllowed();
                $this->fail('Partial approval opened the gate.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Real searches blocked', $exception->getMessage());
            }
        }
    }

    public function test_testing_gate_allows_fakes_and_rejects_unfaked_http(): void
    {
        app(OperationalSearchGate::class)->assertAllowed();
        $this->expectException(\Illuminate\Http\Client\Exceptions\StrayRequestException::class);
        Http::get('https://synthetic.example.test/unfaked');
    }
}
