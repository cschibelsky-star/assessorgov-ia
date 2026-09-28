<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GovComplianceTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_b_cannot_update_compliance_item_owned_only_by_customer_a(): void
    {
        [$userA, $rowA] = $this->scenario('tenant-item');
        $customerB = Customer::factory()->create();
        $userB = User::factory()->forCustomer($customerB)->create();

        $this->actingAs($userB)
            ->put(route('gov.compliance.update', 'tenant-item'), [
                'status' => 'in_review',
                'note' => 'Tentativa cruzada',
            ])
            ->assertRedirect(route('gov.compliance'));

        $this->assertSame('pending', $rowA->refresh()->metadata['gov_intelligence_actions']['tenant-item']['status']);
    }

    public function test_customer_b_cannot_download_customer_a_evidence(): void
    {
        Storage::fake('local');
        [$userA, $rowA] = $this->scenario('tenant-item', [
            'evidence' => [
                'disk' => 'local',
                'path' => 'gov-compliance/evidence.pdf',
                'original_name' => 'evidence.pdf',
            ],
        ]);
        Storage::disk('local')->put('gov-compliance/evidence.pdf', 'evidence');

        $customerB = Customer::factory()->create();
        $userB = User::factory()->forCustomer($customerB)->create();

        $this->actingAs($userB)
            ->get(route('gov.compliance.evidence', [$rowA, 'tenant-item']))
            ->assertForbidden();
    }

    private function scenario(string $item, array $extra = []): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create();
        $action = array_merge([
            'id' => $item,
            'title' => 'Item',
            'action' => 'Revisar',
            'priority' => 'high',
            'impact_class' => 'alter_rule',
            'target' => 'documentos',
            'status' => 'pending',
        ], $extra);

        $row = CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'metadata' => ['gov_intelligence_actions' => [$item => $action]],
        ]);

        return [$user, $row];
    }
}
