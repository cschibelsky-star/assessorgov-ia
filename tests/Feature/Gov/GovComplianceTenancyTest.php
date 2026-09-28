<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\GovComplianceItem;
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
        [$userA, $rowA, $itemA] = $this->scenario('tenant-item');
        $customerB = Customer::factory()->create();
        $userB = User::factory()->forCustomer($customerB)->create();

        $this->actingAs($userB)
            ->put(route('gov.compliance.update', 'tenant-item'), [
                'status' => 'in_review',
                'note' => 'Tentativa cruzada',
            ])
            ->assertRedirect(route('gov.compliance'));

        $this->assertSame('pending', $itemA->refresh()->status);
    }

    public function test_customer_b_cannot_download_customer_a_evidence(): void
    {
        Storage::fake('local');
        [$userA, $rowA, $itemA] = $this->scenario('tenant-item');

        $itemA->evidences()->create([
            'disk' => 'local',
            'path' => 'gov-compliance/evidence.pdf',
            'original_name' => 'evidence.pdf',
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put('gov-compliance/evidence.pdf', 'evidence');

        $customerB = Customer::factory()->create();
        $userB = User::factory()->forCustomer($customerB)->create();

        $this->actingAs($userB)
            ->get(route('gov.compliance.evidence', [$rowA, 'tenant-item']))
            ->assertForbidden();
    }

    private function scenario(string $item): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create();
        $row = CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
        ]);

        $complianceItem = GovComplianceItem::query()->create([
            'customer_id' => $customer->getKey(),
            'item_key' => $item,
            'title' => 'Item',
            'action' => 'Revisar',
            'priority' => 'high',
            'impact_class' => 'alter_rule',
            'target' => 'documentos',
            'status' => 'pending',
        ]);
        $complianceItem->customerOpportunities()->attach($row->getKey());

        return [$user, $row, $complianceItem];
    }
}
