<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DeedStatusService;
use PHPUnit\Framework\TestCase;

final class DeedStatusServiceTest extends TestCase
{
    public function testSubmittedByDefault(): void
    {
        $this->assertSame('Submitted', DeedStatusService::deriveStatus([]));
    }

    public function testReviewedWhenMarkedReviewed(): void
    {
        $this->assertSame('Reviewed', DeedStatusService::deriveStatus([
            'reviewed' => 1,
        ]));
    }

    public function testReceivedWhenMarkedReceived(): void
    {
        $this->assertSame('Received', DeedStatusService::deriveStatus([
            'reviewed' => 1,
            'received' => 1,
        ]));
    }

    public function testReceivedTakesPriorityEvenIfReviewedFlagIsSomehowMissing(): void
    {
        $this->assertSame('Received', DeedStatusService::deriveStatus([
            'received' => 1,
        ]));
    }
}
