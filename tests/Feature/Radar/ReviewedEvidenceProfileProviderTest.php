<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\GovComplianceEvidence;
use App\Models\GovComplianceItem;
use App\Models\User;
use App\Services\Radar\ReviewedEvidenceProfileProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewedEvidenceProfileProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_reviewed_evidence_for_requested_customer_is_loaded_as_pending(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        $reviewer = User::factory()->create();

        foreach ([$customer, $other] as $owner) {
            $item = GovComplianceItem::query()->create([
                'customer_id' => $owner->getKey(),
                'item_key' => 'cnpj',
                'title' => 'CNPJ',
                'action' => 'Verificar documento',
                'priority' => 'medium',
                'impact_class' => 'alter_checklist',
                'target' => 'customer',
                'status' => 'conformant',
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
            ]);
            GovComplianceEvidence::query()->create([
                'gov_compliance_item_id' => $item->getKey(),
                'disk' => 'local',
                'path' => 'tests/fixture.pdf',
                'original_name' => 'fixture.pdf',
                'mime_type' => 'application/pdf',
                'sha256' => (string) $owner->getKey(),
                'size_bytes' => 12,
                'uploaded_at' => now()->subMinute(),
            ]);
        }

        $profile = app(ReviewedEvidenceProfileProvider::class)->forCustomer((string) $customer->getKey());
        $this->assertSame((string) $customer->getKey(), $profile['customer_id']);
        $this->assertSame('pending', $profile['evidence']['cnpj']['status']);
        $this->assertSame((string) $customer->getKey(), $profile['evidence']['cnpj']['reference']);
        $this->assertFalse($profile['activities_complete']);
        $this->assertCount(1, $profile['evidence']);
    }
}
