<?php

namespace Tests\Unit\Radar;

use App\Models\CustomerOpportunity;
use App\Services\Radar\CustomerOpportunityScreeningService;
use App\Services\Radar\ItemEligibilityClassifier;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerOpportunityScreeningServiceTest extends TestCase
{
    public function test_prepares_screening_without_persisting_or_approving(): void
    {
        $record = new CustomerOpportunity([
            'customer_id' => 'tenant-a',
            'opportunity_id' => 'opportunity-1',
            'stage' => CustomerOpportunity::STAGE_ANALYSIS,
            'metadata' => ['original' => 'kept'],
        ]);

        $result = (new CustomerOpportunityScreeningService(new ItemEligibilityClassifier))->prepare(
            $record,
            ['id' => 'item-1', 'activity' => 'software', 'requirements_reviewed' => true, 'requirements' => ['cnpj']],
            ['customer_id' => 'tenant-a', 'activities' => ['software']],
            new DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );

        self::assertSame('pending_evidence', $result['metadata']['item_screening']['classification']);
        self::assertSame('kept', $result['metadata']['original']);
        self::assertContains('cnpj', $result['gaps']);
        self::assertFalse($result['metadata']['item_screening']['proposal_allowed']);
        self::assertFalse($result['metadata']['item_screening']['commercial_notification_allowed']);
        self::assertSame(CustomerOpportunity::STAGE_ANALYSIS, $record->stage);
        self::assertSame(['original' => 'kept'], $record->metadata);
    }

    public function test_rejects_cross_customer_evidence(): void
    {
        $record = new CustomerOpportunity(['customer_id' => 'tenant-a', 'opportunity_id' => 'opportunity-1']);
        $this->expectException(InvalidArgumentException::class);

        (new CustomerOpportunityScreeningService(new ItemEligibilityClassifier))->prepare(
            $record,
            ['id' => 'item-1'],
            ['customer_id' => 'tenant-b'],
            new DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }

    public function test_requires_linked_opportunity(): void
    {
        $record = new CustomerOpportunity(['customer_id' => 'tenant-a']);
        $this->expectException(InvalidArgumentException::class);

        (new CustomerOpportunityScreeningService(new ItemEligibilityClassifier))->prepare(
            $record,
            ['id' => 'item-1'],
            ['customer_id' => 'tenant-a'],
            new DateTimeImmutable('2026-10-13T12:00:00-03:00'),
        );
    }
}
