<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Services\Radar\CustomerOpportunityScreeningPersister;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOpportunityScreeningPersisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_pending_evidence_without_advancing_stage(): void
    {
        $customer = Customer::factory()->create();
        $opportunity = Opportunity::factory()->create();
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
            'metadata' => ['prior' => 'preserved'],
        ]);

        $result = app(CustomerOpportunityScreeningPersister::class)->persist(
            (string) $customer->getKey(),
            (string) $record->getKey(),
            ['id' => 'item-1', 'activity' => 'software', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            ['customer_id' => (string) $customer->getKey(), 'activities' => ['software']],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );

        $this->assertSame(CustomerOpportunity::STAGE_DETECTED, $result->stage);
        $this->assertSame('pending_evidence', $result->metadata['item_screening']['classification']);
        $this->assertSame('preserved', $result->metadata['prior']);
        $this->assertFalse($result->metadata['item_screening']['proposal_allowed']);
        $this->assertFalse($result->metadata['item_screening']['commercial_notification_allowed']);
        $this->assertNull($result->match_score);
        $this->assertDatabaseCount('customer_opportunities', 1);
    }

    public function test_does_not_persist_another_customers_record(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $owner->getKey(),
            'opportunity_id' => Opportunity::factory()->create()->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(CustomerOpportunityScreeningPersister::class)->persist(
            (string) $other->getKey(),
            (string) $record->getKey(),
            ['id' => 'item-1', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            ['customer_id' => (string) $other->getKey()],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }

    public function test_rejects_mismatched_profile_before_database_write(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(CustomerOpportunityScreeningPersister::class)->persist(
            'tenant-a', 'record-1', [], ['customer_id' => 'tenant-b'],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }
}
