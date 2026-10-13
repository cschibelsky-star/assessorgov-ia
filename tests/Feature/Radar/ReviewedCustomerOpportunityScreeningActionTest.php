<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Radar\ReviewedCustomerOpportunityScreeningAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReviewedCustomerOpportunityScreeningActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_screening_uses_server_side_evidence_and_remains_pending(): void
    {
        $customer = Customer::factory()->create();
        $actor = User::factory()->create(['customer_id' => $customer->getKey()]);
        $actor->givePermissionTo(Permission::findOrCreate('radar.screen', 'web'));
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => Opportunity::factory()->create()->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
            'metadata' => ['prior' => 'kept'],
        ]);

        $result = app(ReviewedCustomerOpportunityScreeningAction::class)->execute(
            $actor,
            (string) $record->getKey(),
            ['id' => 'item-1', 'activity' => 'software', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );

        $this->assertSame('pending_evidence', $result->metadata['item_screening']['classification']);
        $this->assertContains('cnpj', $result->gaps);
        $this->assertSame('kept', $result->metadata['prior']);
        $this->assertSame(CustomerOpportunity::STAGE_DETECTED, $result->stage);
        $this->assertFalse($result->metadata['item_screening']['proposal_allowed']);
    }

    public function test_cross_customer_record_is_not_accessible(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $actor = User::factory()->create(['customer_id' => $other->getKey()]);
        $actor->givePermissionTo(Permission::findOrCreate('radar.screen', 'web'));
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $owner->getKey(),
            'opportunity_id' => Opportunity::factory()->create()->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(ReviewedCustomerOpportunityScreeningAction::class)->execute(
            $actor,
            (string) $record->getKey(),
            ['id' => 'item-1', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }

    public function test_actor_without_customer_is_denied(): void
    {
        $actor = User::factory()->create(['customer_id' => null]);
        $this->expectException(AuthorizationException::class);
        app(ReviewedCustomerOpportunityScreeningAction::class)->execute(
            $actor, 'record-1', [], new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }
}
