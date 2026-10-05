<?php

namespace Tests\Feature\Gov;

use App\Models\Customer;
use App\Models\GovComplianceItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovComplianceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_without_review_permission_cannot_access_review_queue(): void
    {
        $user = User::factory()->forCustomer(Customer::factory()->create())->create();

        $this->actingAs($user)
            ->get(route('gov.compliance.review.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_move_submitted_item_to_in_review_and_conformant(): void
    {
        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $reviewer->assignRole('super-admin');

        $item = GovComplianceItem::query()->create([
            'customer_id' => $customer->getKey(),
            'item_key' => 'review-item',
            'title' => 'Revisar documento',
            'action' => 'Validar evidência',
            'priority' => 'high',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->put(route('gov.compliance.review.update', $item), [
                'status' => 'in_review',
                'review_note' => 'Análise iniciada.',
            ])
            ->assertRedirect(route('gov.compliance.review.index'));

        $this->assertSame('in_review', $item->refresh()->status);

        $this->actingAs($reviewer)
            ->put(route('gov.compliance.review.update', $item), [
                'status' => 'conformant',
                'review_note' => 'Evidência validada.',
            ])
            ->assertRedirect(route('gov.compliance.review.index'));

        $item->refresh();
        $this->assertSame('conformant', $item->status);
        $this->assertSame($reviewer->getKey(), $item->reviewed_by_user_id);
        $this->assertNotNull($item->reviewed_at);
        $this->assertNotNull($item->resolved_at);
    }

    public function test_reviewer_can_reject_submitted_item(): void
    {
        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $reviewer->assignRole('super-admin');

        $item = GovComplianceItem::query()->create([
            'customer_id' => $customer->getKey(),
            'item_key' => 'reject-item',
            'title' => 'Documento incompleto',
            'action' => 'Complementar evidência',
            'priority' => 'medium',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->put(route('gov.compliance.review.update', $item), [
                'status' => 'rejected',
                'review_note' => 'Documento ilegível.',
            ])
            ->assertRedirect(route('gov.compliance.review.index'));

        $item->refresh();
        $this->assertSame('rejected', $item->status);
        $this->assertSame('Documento ilegível.', $item->review_note);
    }
}
