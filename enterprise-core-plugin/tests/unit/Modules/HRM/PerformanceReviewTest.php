<?php
declare(strict_types=1);

namespace Enterprise\Tests\Unit\Modules\Hrm;

use Enterprise\Modules\Hrm\PerformanceReview;
use PHPUnit\Framework\TestCase;

final class PerformanceReviewTest extends TestCase
{
    public function testStatusesIncludeAllRequired(): void
    {
        self::assertContains('draft',        PerformanceReview::STATUSES);
        self::assertContains('submitted',    PerformanceReview::STATUSES);
        self::assertContains('acknowledged', PerformanceReview::STATUSES);
        self::assertContains('finalized',    PerformanceReview::STATUSES);
    }

    public function testBoundScoreClampedTo5(): void
    {
        $m = new \ReflectionMethod(PerformanceReview::class, 'boundScore');
        $m->setAccessible(true);
        self::assertSame(0.0,  $m->invoke(null, -1));
        self::assertSame(0.0,  $m->invoke(null, 0));
        self::assertSame(5.0,  $m->invoke(null, 5));
        self::assertSame(5.0,  $m->invoke(null, 99));
        self::assertSame(3.5,  $m->invoke(null, 3.5));
    }

    public function testComputeOverallWeighted(): void
    {
        $m = new \ReflectionMethod(PerformanceReview::class, 'computeOverall');
        $m->setAccessible(true);
        // all 5s → 5.0
        $overall = $m->invoke(null, [
            'productivity_score' => 5,
            'quality_score'      => 5,
            'teamwork_score'     => 5,
            'punctuality_score'  => 5,
        ]);
        self::assertSame(5.0, $overall);
        // all 0s → 0.0
        $overall = $m->invoke(null, [
            'productivity_score' => 0,
            'quality_score'      => 0,
            'teamwork_score'     => 0,
            'punctuality_score'  => 0,
        ]);
        self::assertSame(0.0, $overall);
        // productivity 5, others 0 → 0.4*5 = 2.0
        $overall = $m->invoke(null, [
            'productivity_score' => 5,
            'quality_score'      => 0,
            'teamwork_score'     => 0,
            'punctuality_score'  => 0,
        ]);
        self::assertSame(2.0, $overall);
    }

    public function testStatusConstantsMatchEnum(): void
    {
        self::assertSame('draft',        PerformanceReview::STATUS_DRAFT);
        self::assertSame('submitted',    PerformanceReview::STATUS_SUBMITTED);
        self::assertSame('acknowledged', PerformanceReview::STATUS_ACKNOWLEDGED);
        self::assertSame('finalized',    PerformanceReview::STATUS_FINALIZED);
    }
}
