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
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
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
        $this->assertContains('pncp-search-filters-export-2026-09', $ids);
        $this->assertContains('compras-platform-incident-2026-09', $ids);
        $this->assertContains('tcu-capacity-transfer-2026-09', $ids);
        $this->assertContains('tcu-financial-period-basis-2026-09', $ids);
        $this->assertContains('tcu-execution-evidence-2026-09', $ids);
        $this->assertContains('compras-price-research-lite-2026-10', $ids);
        $this->assertSame(7, $result['stats']['new_changes']);
    }

    public function test_applying_intelligence_persists_machine_readable_rule_on_customer_opportunity(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create(['channel' => Opportunity::CHANNEL_LICITACAO]);
        $row = CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'stage' => CustomerOpportunity::STAGE_PARTICIPATION,
        ]);

        $result = app(GovIntelligenceService::class)->applyToCustomer(
            $user,
            'tcu-capacity-transfer-2026-09',
        );

        $this->assertSame('applied', $result['status']);
        $this->assertSame(1, $result['applied']);

        $rule = $row->refresh()->metadata['gov_intelligence_rules']['tcu-capacity-transfer-2026-09'];

        $this->assertSame('technical_capacity_transfer', $rule['rule_key']);
        $this->assertSame('pending', $rule['status']);
        $this->assertTrue($rule['checks']['transfer_evidence_required_when_cnpj_differs']);
        $this->assertFalse($rule['checks']['shared_partners_alone_are_sufficient']);
        $this->assertNotEmpty($rule['source_url']);
    }
}
