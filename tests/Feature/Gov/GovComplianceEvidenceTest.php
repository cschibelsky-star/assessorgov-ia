<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GovComplianceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_can_be_uploaded_and_downloaded_by_owning_customer(): void
    {
        Storage::fake('local');
        [$user, $row] = $this->scenario();

        $this->actingAs($user)
            ->put(route('gov.compliance.update', 'evidence-item'), [
                'status' => 'in_review',
                'evidence' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $row->refresh();
        $path = $row->metadata['gov_intelligence_actions']['evidence-item']['evidence']['path'];
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)
            ->get(route('gov.compliance.evidence', [$row, 'evidence-item']))
            ->assertOk();
    }

    /** @group known-issue */
    public function test_current_behavior_customer_can_mark_compliance_as_conformant(): void
    {
        [$user, $row] = $this->scenario();

        $this->actingAs($user)
            ->put(route('gov.compliance.update', 'evidence-item'), ['status' => 'conformant'])
            ->assertRedirect();

        $this->assertSame(
            'conformant',
            $row->refresh()->metadata['gov_intelligence_actions']['evidence-item']['status'],
        );
    }

    /** @group known-issue */
    public function test_current_behavior_replacing_evidence_leaves_previous_file_stored(): void
    {
        Storage::fake('local');
        [$user, $row] = $this->scenario();

        $this->actingAs($user)->put(route('gov.compliance.update', 'evidence-item'), [
            'status' => 'in_review',
            'evidence' => UploadedFile::fake()->create('first.pdf', 20, 'application/pdf'),
        ]);
        $row->refresh();
        $firstPath = $row->metadata['gov_intelligence_actions']['evidence-item']['evidence']['path'];

        $this->actingAs($user)->put(route('gov.compliance.update', 'evidence-item'), [
            'status' => 'in_review',
            'evidence' => UploadedFile::fake()->create('second.pdf', 20, 'application/pdf'),
        ]);

        Storage::disk('local')->assertExists($firstPath);
    }

    private function scenario(): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->forCustomer($customer)->create();
        $opportunity = Opportunity::factory()->create();
        $row = CustomerOpportunity::factory()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'metadata' => [
                'gov_intelligence_actions' => [
                    'evidence-item' => [
                        'id' => 'evidence-item',
                        'title' => 'Evidência',
                        'action' => 'Enviar documento',
                        'priority' => 'high',
                        'impact_class' => 'alter_rule',
                        'target' => 'documentos',
                        'status' => 'pending',
                    ],
                ],
            ],
        ]);

        return [$user, $row];
    }
}
