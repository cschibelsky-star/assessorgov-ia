<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Radar\CustomerOpportunityScreeningAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerOpportunityScreeningActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_screen_only_its_record(): void
    {
        $customer = Customer::factory()->create();
        $actor = User::factory()->create(['customer_id' => $customer->getKey()]);
        $actor->givePermissionTo(Permission::findOrCreate('radar.screen', 'web'));
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => Opportunity::factory()->create()->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
        ]);

        $result = app(CustomerOpportunityScreeningAction::class)->execute(
            $actor,
            (string) $record->getKey(),
            ['id' => 'item-1', 'activity' => 'software', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            ['customer_id' => (string) $customer->getKey(), 'activities' => ['software']],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );

        $this->assertSame('pending_evidence', $result->metadata['item_screening']['classification']);
        $this->assertSame(CustomerOpportunity::STAGE_DETECTED, $result->stage);
        $this->assertFalse($result->metadata['item_screening']['commercial_notification_allowed']);
    }

    public function test_rejects_profile_of_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $actor = User::factory()->create(['customer_id' => $customer->getKey()]);
        $actor->givePermissionTo(Permission::findOrCreate('radar.screen', 'web'));
        $this->expectException(AuthorizationException::class);

        app(CustomerOpportunityScreeningAction::class)->execute(
            $actor,
            'record-1',
            [],
            ['customer_id' => (string) $other->getKey()],
            new \DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }
}
