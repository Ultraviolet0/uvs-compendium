<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uvs\Guides\GuideStatus;
use Uvs\Guides\LineDiff;

final class DiffAndStatusTest extends TestCase
{
    public function testLineDiff(): void
    {
        $ops = LineDiff::compare("a\nb\nc", "a\nB\nc\nd");
        self::assertSame([[' ', 'a'], ['-', 'b'], ['+', 'B'], [' ', 'c'], ['+', 'd']], $ops);
        $context = LineDiff::withContext(LineDiff::compare(implode("\n", range(1, 30)), implode("\n", array_merge(range(1, 15), ['x'], range(17, 30)))), 1);
        self::assertContains(['…', ''], $context);
        self::assertLessThan(10, count($context));
    }

    public function testStatusLabelsAndAuthorPermissions(): void
    {
        $base = ['deleted_at' => null, 'first_published_at' => null, 'review_status' => 'draft', 'visibility' => 'private'];
        self::assertSame('Draft', GuideStatus::describe($base)['label']);
        self::assertTrue(GuideStatus::authorCanEdit($base));
        self::assertTrue(GuideStatus::authorCanSubmit($base));
        self::assertTrue(GuideStatus::authorCanDelete($base));

        $review = ['review_status' => 'in_review'] + $base;
        self::assertSame('In review', GuideStatus::describe($review)['label']);
        self::assertFalse(GuideStatus::authorCanEdit($review));
        self::assertTrue(GuideStatus::authorCanWithdraw($review));

        $rejected = ['review_status' => 'rejected'] + $base;
        self::assertFalse(GuideStatus::authorCanEdit($rejected));
        self::assertFalse(GuideStatus::authorCanSubmit($rejected));

        $live = ['review_status' => 'approved', 'visibility' => 'published', 'first_published_at' => '2026-10-01 00:00:00'] + $base;
        self::assertSame('Published', GuideStatus::describe($live)['label']);
        self::assertTrue(GuideStatus::authorCanEdit($live), 'editing a live guide starts an update draft');
        self::assertFalse(GuideStatus::authorCanDelete($live));
        self::assertFalse(GuideStatus::authorCanWithdraw($live));

        self::assertSame('Hidden', GuideStatus::describe(['visibility' => 'hidden'] + $live)['label']);
        self::assertSame('Deleted', GuideStatus::describe(['deleted_at' => '2026-10-02 00:00:00'] + $live)['label']);
        self::assertFalse(GuideStatus::authorCanEdit(['deleted_at' => '2026-10-02 00:00:00'] + $base));
    }
}
