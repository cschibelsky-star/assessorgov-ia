<?php

namespace Tests\Unit\Radar;

use App\Services\Radar\ItemEligibilityClassifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ItemEligibilityClassifierTest extends TestCase
{
    private function classify(array $profile, array $changes = []): array
    {
        return (new ItemEligibilityClassifier)->classify(array_replace([
            'activity' => 'computer_supply',
            'requirements_reviewed' => true,
            'requirements' => ['legal_activity', 'tax_clearance', 'delivery', 'technical_catalog'],
        ], $changes), $profile, new DateTimeImmutable('2026-10-09T14:00:00-03:00'));
    }

    public function test_taiacu_official_snapshot_does_not_match_a_synthetic_digital_customer(): void
    {
        $payload = json_decode(file_get_contents(__DIR__.'/../../Fixtures/pncp/taiacu-publicacao.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('44544690000115-1-000136/2026', $payload['data'][0]['numeroControlePNCP']);
        $profile = ['activities' => ['software', 'automation', 'digital_content'], 'activities_complete' => true];
        foreach (['climate_supply', 'chair_supply', 'computer_supply', 'projector_supply'] as $activity) {
            $result = $this->classify($profile, ['activity' => $activity]);
            $this->assertSame('outside_profile', $result['classification']);
            $this->assertFalse($result['commercial_notification_allowed']);
        }
    }

    public function test_digital_services_do_not_imply_equipment_supply(): void
    {
        $result = $this->classify(['activities' => ['software', 'automation', 'digital_content'], 'activities_complete' => true]);
        $this->assertSame('outside_profile', $result['classification']);
        $this->assertSame('pending_evidence', $result['eligibility']);
        $this->assertContains('legal_activity', $result['gaps']);
        $this->assertNotEmpty($result['match_reasons']);
        $this->assertNull($result['match_score']);
        $this->assertFalse($result['proposal_allowed']);
        $this->assertFalse($result['commercial_notification_allowed']);
    }

    public function test_declared_supply_is_not_verified_habilitation(): void
    {
        $result = $this->classify(['activities' => ['computer_supply']]);
        $this->assertSame('compatible', $result['commercial_fit']);
        $this->assertSame('pending_evidence', $result['classification']);
    }

    public function test_incomplete_profile_is_not_classified_as_incompatible(): void
    {
        $this->assertSame('unconfirmed', $this->classify([])['commercial_fit']);
    }

    public function test_verified_evidence_only_allows_analysis_and_does_not_mutate_inputs(): void
    {
        $profile = ['activities' => ['computer_supply'], 'evidence' => []];
        foreach (['legal_activity', 'tax_clearance', 'delivery', 'technical_catalog'] as $key) {
            $profile['evidence'][$key] = ['status' => 'verified', 'reference' => 'synthetic:'.$key, 'valid_until' => '2026-10-16'];
        }
        $original = $profile;
        $result = $this->classify($profile);
        $this->assertSame('compatible_for_analysis', $result['classification']);
        $this->assertSame([], $result['gaps']);
        $this->assertSame($original, $profile);
        $this->assertFalse($result['proposal_allowed']);
        $profile['evidence']['tax_clearance']['valid_until'] = '2026-10-08';
        $this->assertContains('tax_clearance', $this->classify($profile)['gaps']);
        $profile['evidence']['tax_clearance']['status'] = 'failed';
        $this->assertSame('blocked', $this->classify($profile)['classification']);
    }

    public function test_projector_requires_own_activity_and_me_epp_evidence(): void
    {
        $result = $this->classify(['activities' => ['projector_supply']], [
            'activity' => 'projector_supply', 'requirements' => ['legal_activity', 'me_epp'],
        ]);
        $this->assertSame('compatible', $result['commercial_fit']);
        $this->assertContains('me_epp', $result['gaps']);
        $this->assertSame('unconfirmed', $this->classify(['activities' => ['computer_supply']], ['activity' => 'chair_supply'])['commercial_fit']);
    }

    public function test_missing_review_or_unreferenced_evidence_fails_closed(): void
    {
        $profile = ['activities' => ['computer_supply'], 'evidence' => ['legal_activity' => ['status' => 'verified']]];
        $this->assertContains('legal_activity', $this->classify($profile)['gaps']);
        $this->assertContains('requirements_review', $this->classify($profile, ['requirements_reviewed' => false])['gaps']);
    }
}
