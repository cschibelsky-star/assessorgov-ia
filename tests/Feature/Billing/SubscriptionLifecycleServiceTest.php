<?php

namespace Tests\Feature\Billing;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_subscription_moves_through_operational_stages_without_losing_preservation(): void
    {
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');
        $subscription = $this->subscription();
        $service = app(SubscriptionLifecycleService::class);

        $service->markPastDue($subscription, CarbonImmutable::parse('2026-09-01 12:00:00'));
        $this->assertSame(Subscription::OPERATIONAL_GRACE, $subscription->refresh()->operational_status);
        $this->assertTrue($subscription->refresh()->preservesExistingWork());

        $service->recalculateOperationalStatus($subscription->refresh(), CarbonImmutable::parse('2026-09-09 12:00:00'));
        $this->assertSame(Subscription::OPERATIONAL_RESTRICTED, $subscription->refresh()->operational_status);
        $this->assertFalse($subscription->refresh()->canCreateNewWork());

        $service->recalculateOperationalStatus($subscription->refresh(), CarbonImmutable::parse('2026-09-17 12:00:00'));
        $this->assertSame(Subscription::OPERATIONAL_PRESERVATION, $subscription->refresh()->operational_status);
        $this->assertFalse($subscription->refresh()->canUseAi());

        $service->recalculateOperationalStatus($subscription->refresh(), CarbonImmutable::parse('2026-10-02 12:00:00'));
        $subscription->refresh();
        $this->assertSame(Subscription::BILLING_SUSPENDED, $subscription->billing_status);
        $this->assertSame(Subscription::OPERATIONAL_PRESERVATION, $subscription->operational_status);
        $this->assertTrue($subscription->preservesExistingWork());
    }

    public function test_payment_reactivates_subscription_and_clears_delinquency_restrictions(): void
    {
        $subscription = $this->subscription();
        $service = app(SubscriptionLifecycleService::class);

        $service->markPastDue($subscription, CarbonImmutable::parse('2026-08-15 12:00:00'));
        $service->recalculateOperationalStatus($subscription->refresh(), CarbonImmutable::parse('2026-09-01 12:00:00'));
        $service->markPaid($subscription->refresh());

        $subscription->refresh();
        $this->assertSame(Subscription::BILLING_ACTIVE, $subscription->billing_status);
        $this->assertSame(Subscription::OPERATIONAL_NORMAL, $subscription->operational_status);
        $this->assertNull($subscription->past_due_at);
        $this->assertTrue($subscription->canCreateNewWork());
        $this->assertTrue($subscription->canUseAi());
    }

    private function subscription(): Subscription
    {
        $customer = Customer::query()->create([
            'legal_name' => 'Cliente Teste Ltda',
            'document' => '00000000000191',
            'status' => 'active',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Profissional',
            'slug' => 'profissional-test',
            'price' => 197,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        return Subscription::query()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_status' => Subscription::BILLING_ACTIVE,
            'operational_status' => Subscription::OPERATIONAL_NORMAL,
        ]);
    }
}
