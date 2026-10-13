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
            'id' => 'taiacu:3',
            'activity' => 'computer_supply',
            'requirements_reviewed' => true,
            'requirements' => ['legal_activity', 'tax_clearance', 'delivery', 'technical_catalog'],
        ], $changes), array_replace(['customer_id' => 'synthetic:a'], $profile), new DateTimeImmutable('2026-10-09T14:00:00-03:00'));
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
            $profile['evidence'][$key] = ['customer_id' => 'synthetic:a', 'status' => 'verified', 'reference' => 'synthetic:'.$key, 'valid_until' => '2026-10-16'];
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

    public function test_evidence_cannot_cross_customers_or_items(): void
    {
        $item = ['requirements' => [['key' => 'technical_catalog', 'scope' => 'item', 'expires' => false]]];
        $profile = ['activities' => ['computer_supply'], 'evidence' => [
            'technical_catalog' => ['customer_id' => 'synthetic:b', 'item_id' => 'taiacu:3', 'status' => 'verified', 'reference' => 'synthetic:catalog'],
        ]];
        $this->assertSame('pending_evidence', $this->classify($profile, $item)['classification']);
        $profile['evidence']['technical_catalog']['customer_id'] = 'synthetic:a';
        $profile['evidence']['technical_catalog']['item_id'] = 'taiacu:4';
        $this->assertSame('pending_evidence', $this->classify($profile, $item)['classification']);
        $profile['evidence']['technical_catalog']['item_id'] = 'taiacu:3';
        $this->assertSame('compatible_for_analysis', $this->classify($profile, $item)['classification']);
        $profile['customer_id'] = '';
        $this->assertSame('pending_evidence', $this->classify($profile, $item)['classification']);
    }

    public function test_invalid_dates_and_references_fail_closed(): void
    {
        foreach (['2026-02-30', '2026-13-01', '0000-00-00', 'invalid', null, '2026-10-12'] as $date) {
            $result = (new ItemEligibilityClassifier)->classify([
                'activity' => 'computer_supply', 'requirements_reviewed' => true, 'requirements' => ['tax'],
            ], ['customer_id' => 'synthetic:a', 'activities' => ['computer_supply'], 'evidence' => [
                'tax' => ['customer_id' => 'synthetic:a', 'status' => 'verified', 'reference' => 'synthetic:tax', 'valid_until' => $date],
            ]], new DateTimeImmutable('2026-10-13T13:00:00Z'));
            $this->assertSame('pending_evidence', $result['classification']);
        }
        $this->assertContains('requirements_schema', $this->classify([], ['requirements' => [null, []]])['gaps']);
        $this->assertSame('unconfirmed', $this->classify(['activities_complete' => true], ['activity' => ''])['commercial_fit']);
    }

    public function test_brasilia_expiry_and_commercial_fit_are_independent_of_eligibility(): void
    {
        $item = ['activity' => 'computer_supply', 'requirements_reviewed' => true, 'requirements' => ['tax']];
        $profile = ['customer_id' => 'synthetic:a', 'activities_complete' => true, 'activities' => ['software'], 'evidence' => [
            'tax' => ['customer_id' => 'synthetic:a', 'status' => 'verified', 'reference' => 'synthetic:tax', 'valid_until' => '2026-10-13'],
        ]];
        $result = (new ItemEligibilityClassifier)->classify($item, $profile, new DateTimeImmutable('2026-10-14T01:00:00Z'));
        $this->assertSame('outside_profile', $result['classification']);
        $this->assertSame('verified_for_prescreen', $result['eligibility']);
        $result = (new ItemEligibilityClassifier)->classify($item, $profile, new DateTimeImmutable('2026-10-14T03:00:00Z'));
        $this->assertSame('pending_evidence', $result['eligibility']);
    }

    public function test_failed_fiscal_evidence_for_verified_me_epp_requires_regularization_review(): void
    {
        $profile = ['activities' => ['computer_supply'], 'me_epp_verified' => true, 'evidence' => [
            'tax' => ['customer_id' => 'synthetic:a', 'status' => 'failed', 'reference' => 'synthetic:tax'],
        ]];
        $item = ['requirements' => [['key' => 'tax', 'me_epp_regularization' => true]]];
        $this->assertSame('pending_evidence', $this->classify($profile, $item)['classification']);
        $profile['me_epp_verified'] = false;
        $this->assertSame('blocked', $this->classify($profile, $item)['classification']);
        $profile['evidence']['tax']['customer_id'] = 'synthetic:b';
        $this->assertSame('pending_evidence', $this->classify($profile, $item)['classification']);
    }

    public function test_reviewed_taiacu_items_with_synthetic_evidence_matrix(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../../Fixtures/pncp/taiacu-matching-reviewed.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('2026-10-16T08:59:00-03:00', $fixture['proposal_deadline']);
        $total = 0;
        foreach ($fixture['items'] as $item) {
            $total += $item['quantity'] * $item['unit_price'];
            $profile = ['customer_id' => 'synthetic:a', 'activities' => [$item['activity']], 'activities_complete' => true, 'evidence' => []];
            foreach ($item['requirements'] as $rule) {
                $profile['evidence'][$rule['key']] = [
                    'customer_id' => 'synthetic:a', 'item_id' => $item['id'], 'status' => 'verified',
                    'reference' => 'synthetic:'.$rule['key'], 'valid_until' => '2026-10-16',
                ];
            }
            $classifier = new ItemEligibilityClassifier;
            $at = new DateTimeImmutable('2026-10-13T10:00:00-03:00');
            $original = $profile;
            $result = $classifier->classify($item, $profile, $at);
            $this->assertSame('compatible_for_analysis', $result['classification']);
            $this->assertFalse($result['proposal_allowed']);
            $this->assertFalse($result['commercial_notification_allowed']);
            $this->assertSame($original, $profile);
            foreach ($item['requirements'] as $rule) {
                $key = $rule['key'];
                $missing = $profile;
                unset($missing['evidence'][$key]);
                $this->assertSame('pending_evidence', $classifier->classify($item, $missing, $at)['classification']);
                $expired = $profile;
                $expired['evidence'][$key]['valid_until'] = '2026-10-12';
                $this->assertSame('pending_evidence', $classifier->classify($item, $expired, $at)['classification']);
                $wrong = $profile;
                $wrong['evidence'][$key]['customer_id'] = 'synthetic:b';
                $this->assertSame('pending_evidence', $classifier->classify($item, $wrong, $at)['classification']);
                $failed = $profile;
                $failed['evidence'][$key]['status'] = 'failed';
                $this->assertSame('blocked', $classifier->classify($item, $failed, $at)['classification']);
            }
            $profile['activities'] = ['software', 'automation', 'digital_content'];
            $this->assertSame('outside_profile', $classifier->classify($item, $profile, $at)['classification']);
        }
        $this->assertEqualsWithDelta(84882.67, $total, 0.001);
        $this->assertContains('me_epp', array_column($fixture['items'][3]['requirements'], 'key'));
        foreach (array_slice($fixture['items'], 0, 3) as $item) {
            $this->assertNotContains('me_epp', array_column($item['requirements'], 'key'));
        }
    }
}
