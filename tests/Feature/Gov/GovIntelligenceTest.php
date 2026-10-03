<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Gov\GovIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GovIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_customer_profile_returns_expected_current_catalog(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create(['channel' => Opportunity::CHANNEL_LICITACAO]);

        CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'stage' => CustomerOpportunity::STAGE_PARTICIPATION,
        ]);

        $result = app(GovIntelligenceService::class)->forUser($user);
        $ids = collect($result['items'])->pluck('id');

        $this->assertContains('tcu-staffing-feasibility-2026-09', $ids);
        $this->assertContains('tcu-zero-cost-2026-09', $ids);
        $this->assertContains('tcu-economic-group-conflict-2026-09', $ids);
        $this->assertContains('sicx-2026', $ids);
        $this->assertContains('irp-pncp-2026', $ids);
        $this->assertContains('tcu-spreadsheet-diligence-2026-09', $ids);
        $this->assertContains('tcu-unusual-financial-index-2026-09', $ids);
        $this->assertContains('compras-app-alerts-2026-09', $ids);
        $this->assertContains('contratos-nfe-xml-2026-09', $ids);
        $this->assertContains('compras-publicidade-lei-12232-2026-09', $ids);
        $this->assertContains('serpro-tax-intelligence-subsidy-2026-09', $ids);
        $this->assertContains('regularize-govbr-2026-09', $ids);
        $this->assertContains('simples-ibs-cbs-option-2026-09', $ids);
        $this->assertSame(7, $result['stats']['new_changes']);
    }
}
