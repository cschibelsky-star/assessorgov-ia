<?php

namespace Tests\Feature\Radar;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\GovComplianceEvidence;
use App\Models\GovComplianceItem;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Radar\CustomerOpportunityScreeningAction;
use App\Services\Radar\CustomerOpportunityScreeningPersister;
use App\Services\Radar\ReviewedCustomerOpportunityScreeningAction;
use App\Services\Radar\ReviewedEvidenceProfileProvider;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssessorGovSafetyGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_vitrine_pilot_stays_isolated_and_never_sends_notifications(): void
    {
        Http::preventStrayRequests();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(15, 0));
        $customer = Customer::factory()->create(['legal_name' => 'Vitrine IA Pro — SYNTHETIC HML']);
        $other = Customer::factory()->create();
        $opportunity = Opportunity::factory()->create([
            'source_url' => 'https://synthetic.example.test/opportunity',
            'source_checked_at' => now()->subMinute(),
            'closes_at' => now()->addDay(),
        ]);
        $record = CustomerOpportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
            'metadata' => ['synthetic' => true],
        ]);
        $item = [
            'id' => 'synthetic:item-1',
            'opportunity_id' => (string) $opportunity->getKey(),
            'activity' => 'software',
            'requirements_reviewed' => true,
            'requirements' => ['cnpj'],
        ];
        $profile = [
            'customer_id' => (string) $customer->getKey(),
            'activities' => ['software'],
            'activities_complete' => true,
            'evidence' => ['cnpj' => [
                'customer_id' => (string) $customer->getKey(),
                'status' => 'verified',
                'reference' => 'synthetic:cnpj',
                'valid_until' => '2026-10-14',
            ]],
        ];
        $at = new DateTimeImmutable('2026-10-13T15:00:00Z');
        $persist = fn (array $i, array $p) => app(CustomerOpportunityScreeningPersister::class)->persist(
            (string) $customer->getKey(), (string) $record->getKey(), $i, $p, $at,
        );
        $result = $persist($item, $profile);
        $this->assertSame('compatible_for_analysis', $result->metadata['item_screening']['classification']);
        $this->assertFalse($result->metadata['item_screening']['proposal_allowed']);
        $this->assertFalse($result->metadata['item_screening']['commercial_notification_allowed']);

        $wrongEvidence = $profile;
        $wrongEvidence['evidence']['cnpj']['customer_id'] = (string) $other->getKey();
        $this->assertSame('pending_evidence', $persist($item, $wrongEvidence)->metadata['item_screening']['classification']);
        $expired = $profile;
        $expired['evidence']['cnpj']['valid_until'] = '2026-10-12';
        $this->assertSame('pending_evidence', $persist($item, $expired)->metadata['item_screening']['classification']);
        $incompatible = $profile;
        $incompatible['activities'] = ['chair_supply'];
        $this->assertSame('outside_profile', $persist($item, $incompatible)->metadata['item_screening']['classification']);
        $wrongItem = $item;
        $wrongItem['opportunity_id'] = 'synthetic:another-opportunity';
        $this->assertContains('item_opportunity_scope', $persist($wrongItem, $profile)->gaps);
        $unreviewed = $item;
        $unreviewed['requirements_reviewed'] = false;
        $this->assertSame('pending_evidence', $persist($unreviewed, $profile)->metadata['item_screening']['classification']);

        foreach ([
            ['closes_at' => now()],
            ['closes_at' => null],
            ['closes_at' => now()->addDay(), 'source_checked_at' => null],
            ['source_checked_at' => now()->subMinute(), 'status' => 'review'],
        ] as $changes) {
            $opportunity->update($changes);
            $result = $persist($item, $profile);
            $this->assertSame('pending_evidence', $result->metadata['item_screening']['classification']);
            $this->assertFalse($result->metadata['item_screening']['proposal_allowed']);
            $this->assertSame(CustomerOpportunity::STAGE_DETECTED, $result->stage);
            $this->assertTrue($result->metadata['synthetic']);
        }
        $this->assertDatabaseCount('customer_opportunities', 1);
        Notification::assertNothingSent();
    }

    public function test_screening_denies_inactive_unpersisted_stale_and_unprivileged_actors(): void
    {
        $customer = Customer::factory()->create();
        $permission = Permission::findOrCreate('radar.screen', 'web');
        $unprivileged = User::factory()->create(['customer_id' => $customer->getKey()]);
        $inactive = User::factory()->create(['customer_id' => $customer->getKey(), 'status' => 'inactive']);
        $inactive->givePermissionTo($permission);
        $stale = User::factory()->create(['customer_id' => $customer->getKey()]);
        $stale->givePermissionTo($permission);
        User::query()->whereKey($stale->getKey())->update(['status' => 'inactive']);
        $unsaved = new User(['customer_id' => $customer->getKey(), 'status' => 'active']);

        foreach ([$unprivileged, $inactive, $stale, $unsaved] as $actor) {
            foreach ([CustomerOpportunityScreeningAction::class, ReviewedCustomerOpportunityScreeningAction::class] as $action) {
                try {
                    $at = new DateTimeImmutable('2026-10-13T15:00:00Z');
                    if ($action === CustomerOpportunityScreeningAction::class) {
                        app($action)->execute($actor, 'synthetic:record', [], ['customer_id' => (string) $customer->getKey()], $at);
                    } else {
                        app($action)->execute($actor, 'synthetic:record', [], $at);
                    }
                    $this->fail('Unauthorized screening was allowed.');
                } catch (AuthorizationException) {
                    $this->assertDatabaseCount('customer_opportunities', 0);
                }
            }
        }
    }

    public function test_document_uploaded_after_review_is_not_loaded_as_reviewed_evidence(): void
    {
        $customer = Customer::factory()->create();
        $reviewer = User::factory()->create();
        $item = GovComplianceItem::query()->create([
            'customer_id' => $customer->getKey(),
            'item_key' => 'cnpj',
            'title' => 'Synthetic CNPJ',
            'action' => 'Review synthetic document',
            'priority' => 'medium',
            'impact_class' => 'alter_checklist',
            'target' => 'customer',
            'status' => 'conformant',
            'reviewed_by_user_id' => $reviewer->getKey(),
            'reviewed_at' => now()->subMinute(),
        ]);
        $evidence = GovComplianceEvidence::query()->create([
            'gov_compliance_item_id' => $item->getKey(),
            'disk' => 'local',
            'path' => 'synthetic/document.pdf',
            'original_name' => 'synthetic.pdf',
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'synthetic'),
            'size_bytes' => 12,
            'uploaded_at' => now(),
        ]);
        $provider = app(ReviewedEvidenceProfileProvider::class);
        $this->assertSame([], $provider->forCustomer((string) $customer->getKey())['evidence']);
        $evidence->update(['uploaded_at' => null]);
        $this->assertSame([], $provider->forCustomer((string) $customer->getKey())['evidence']);
        $evidence->update(['uploaded_at' => now()->subMinutes(2)]);
        $this->assertSame('pending', $provider->forCustomer((string) $customer->getKey())['evidence']['cnpj']['status']);
    }
}
