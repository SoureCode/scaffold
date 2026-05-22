<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Gate;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Gate\TimeWindowGate;
use Symfony\Component\Clock\MockClock;

final class TimeWindowGateTest extends TestCase
{
    public function testWithinWindowReturnsTrue(): void
    {
        $clock = new MockClock('2026-05-21T12:00:00+00:00');
        $gate = new TimeWindowGate($clock, [
            'spring.sale' => [
                'from' => new \DateTimeImmutable('2026-05-01T00:00:00+00:00'),
                'until' => new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
            ],
        ]);

        self::assertTrue($gate->decide('spring.sale'));
    }

    public function testBeforeFromReturnsFalse(): void
    {
        $clock = new MockClock('2026-04-30T23:59:59+00:00');
        $gate = new TimeWindowGate($clock, [
            'spring.sale' => [
                'from' => new \DateTimeImmutable('2026-05-01T00:00:00+00:00'),
                'until' => new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
            ],
        ]);

        self::assertFalse($gate->decide('spring.sale'));
    }

    public function testAtUntilReturnsFalse(): void
    {
        $clock = new MockClock('2026-06-01T00:00:00+00:00');
        $gate = new TimeWindowGate($clock, [
            'spring.sale' => [
                'from' => new \DateTimeImmutable('2026-05-01T00:00:00+00:00'),
                'until' => new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
            ],
        ]);

        self::assertFalse($gate->decide('spring.sale'), 'until is exclusive');
    }

    public function testOpenEndedFromAcceptsAnyTimeBeforeUntil(): void
    {
        $clock = new MockClock('2020-01-01T00:00:00+00:00');
        $gate = new TimeWindowGate($clock, [
            'spring.sale' => ['until' => new \DateTimeImmutable('2026-06-01T00:00:00+00:00')],
        ]);

        self::assertTrue($gate->decide('spring.sale'));
    }

    public function testOpenEndedUntilAcceptsAnyTimeAfterFrom(): void
    {
        $clock = new MockClock('2030-01-01T00:00:00+00:00');
        $gate = new TimeWindowGate($clock, [
            'spring.sale' => ['from' => new \DateTimeImmutable('2026-05-01T00:00:00+00:00')],
        ]);

        self::assertTrue($gate->decide('spring.sale'));
    }

    public function testFullyOpenWindowAlwaysTrue(): void
    {
        $gate = new TimeWindowGate(new MockClock(), ['spring.sale' => []]);

        self::assertTrue($gate->decide('spring.sale'));
    }

    public function testUnknownFlagAbstains(): void
    {
        $gate = new TimeWindowGate(new MockClock(), ['spring.sale' => []]);

        self::assertNull($gate->decide('other.flag'));
    }
}
