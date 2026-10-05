<?php

namespace Tests\Feature\Radar;

use App\Models\Opportunity;
use App\Services\Radar\OpportunityUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityUpserterTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_source_name_and_external_id_is_idempotent(): void
    {
        $service = app(OpportunityUpserter::class);
        $payload = Opportunity::factory()->make([
            'source_name' => 'pncp:contratacoes',
            'external_id' => 'PNCP-123',
        ])->getAttributes();

        $service->upsert($payload);
        $payload['title'] = 'Título atualizado';
        $service->upsert($payload);

        $this->assertDatabaseCount('opportunities', 1);
        $this->assertDatabaseHas('opportunities', [
            'source_name' => 'pncp:contratacoes',
            'external_id' => 'PNCP-123',
            'title' => 'Título atualizado',
        ]);
    }

    public function test_same_external_id_from_different_source_names_creates_two_rows(): void
    {
        $service = app(OpportunityUpserter::class);
        $first = Opportunity::factory()->make([
            'source_name' => 'pncp:contratacoes',
            'external_id' => 'SHARED-1',
        ])->getAttributes();
        $second = Opportunity::factory()->make([
            'source_name' => 'cultura:official',
            'external_id' => 'SHARED-1',
        ])->getAttributes();

        $service->upsert($first);
        $service->upsert($second);

        $this->assertDatabaseCount('opportunities', 2);
    }
}
