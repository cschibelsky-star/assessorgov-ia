<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\GovComplianceItem;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class GovComplianceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_can_be_uploaded_and_downloaded_by_owning_customer(): void
    {
        Storage::fake('local');
        [$user, $row, $item] = $this->scenario();

        $this->actingAs($user)
            ->put(route('gov.compliance.update', 'evidence-item'), [
                'status' => 'in_review',
                'evidence' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $evidence = $item->refresh()->evidences()->latest('id')->first();

        $this->assertNotNull($evidence);
        $this->assertNotNull($evidence->sha256);
        Storage::disk('local')->assertExists($evidence->path);

        $this->actingAs($user)
            ->get(route('gov.compliance.evidence', [$row, 'evidence-item']))
            ->assertOk();
    }

    #[Group('known-issue')]
    public function test_current_behavior_customer_can_mark_compliance_as_conformant(): void
    {
        [$user, $row, $item] = $this->scenario();

        $this->actingAs($user)
            ->put(route('gov.compliance.update', 'evidence-item'), ['status' => 'conformant'])
            ->assertRedirect();

        $this->assertSame('conformant', $item->refresh()->status);
    }

    public function test_replacing_evidence_keeps_versions_instead_of_overwriting_history(): void
    {
        Storage::fake('local');
        [$user, $row, $item] = $this->scenario();

        $this->actingAs($user)->put(route('gov.compliance.update', 'evidence-item'), [
            'status' => 'in_review',
            'evidence' => UploadedFile::fake()->create('first.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($user)->put(route('gov.compliance.update', 'evidence-item'), [
            'status' => 'in_review',
            'evidence' => UploadedFile::fake()->create('second.pdf', 20, 'application/pdf'),
        ]);

        $this->assertSame(2, $item->evidences()->count());
        $item->evidences->each(fn ($evidence) => Storage::disk('local')->assertExists($evidence->path));
    }

    private function scenario(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create();
        $row = CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
        ]);

        $item = GovComplianceItem::query()->create([
            'customer_id' => $customer->getKey(),
            'item_key' => 'evidence-item',
            'title' => 'Evidência',
            'action' => 'Enviar documento',
            'priority' => 'high',
            'impact_class' => 'alter_rule',
            'target' => 'documentos',
            'status' => 'pending',
        ]);
        $item->customerOpportunities()->attach($row->getKey());

        return [$user, $row, $item];
    }
}
