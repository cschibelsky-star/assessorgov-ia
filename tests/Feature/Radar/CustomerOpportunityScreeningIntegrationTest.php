<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Services\Radar\CustomerOpportunityScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOpportunityScreeningIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_screening_is_prepared_without_persisting_or_crossing_tenants(): void
    {
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        $opportunity = Opportunity::factory()->create();
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $customerA->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
            'metadata' => ['previous' => 'kept'],
        ]);

        $service = app(CustomerOpportunityScreeningService::class);
        $item = [
            'id' => 'item-1',
            'activity' => 'software',
            'requirements_reviewed' => true,
            'requirements' => ['cnpj'],
        ];
        $at = new \DateTimeImmutable('2026-10-13T12:00:00-03:00');
        $result = $service->prepare($record, $item, [
            'customer_id' => (string) $customerA->getKey(),
            'activities' => ['software'],
        ], $at);

        $this->assertSame('pending_evidence', $result['metadata']['item_screening']['classification']);
        $this->assertSame('kept', $result['metadata']['previous']);
        $this->assertFalse($result['metadata']['item_screening']['proposal_allowed']);
        $this->assertDatabaseHas('customer_opportunities', [
            'id' => $record->getKey(),
            'customer_id' => $customerA->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
        ]);
        $this->assertSame(['previous' => 'kept'], $record->fresh()->metadata);
        $this->assertDatabaseCount('customer_opportunities', 1);

        $this->expectException(\InvalidArgumentException::class);
        $service->prepare($record, $item, [
            'customer_id' => (string) $customerB->getKey(),
            'activities' => ['software'],
        ], $at);
    }
}
