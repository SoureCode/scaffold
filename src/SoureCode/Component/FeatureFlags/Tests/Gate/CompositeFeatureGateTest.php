<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Gate;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Gate\CompositeFeatureGate;
use SoureCode\Component\FeatureFlags\Tests\Support\FixedVerdictGate;
use SoureCode\Component\FeatureFlags\Tests\Support\RecordingGate;

final class CompositeFeatureGateTest extends TestCase
{
    public function testFirstNonNullVerdictWins(): void
    {
        $composite = new CompositeFeatureGate([
            new FixedVerdictGate('flag', null),
            new FixedVerdictGate('flag', true),
            new FixedVerdictGate('flag', false),
        ]);

        self::assertTrue($composite->decide('flag'));
    }

    public function testReturnsNullWhenAllGatesAbstain(): void
    {
        $composite = new CompositeFeatureGate([
            new FixedVerdictGate('flag', null),
            new FixedVerdictGate('flag', null),
        ]);

        self::assertNull($composite->decide('flag'));
    }

    public function testEmptyGateListAlwaysAbstains(): void
    {
        $composite = new CompositeFeatureGate([]);

        self::assertNull($composite->decide('flag'));
    }

    public function testGatesAreEvaluatedInOrderUntilDecision(): void
    {
        $log = new \ArrayObject();

        $composite = new CompositeFeatureGate([
            new RecordingGate('a', null, $log),
            new RecordingGate('b', true, $log),
            new RecordingGate('c', false, $log),
        ]);

        $composite->decide('flag');

        self::assertSame(['a', 'b'], array_values($log->getArrayCopy()), 'composite stops as soon as a gate returns non-null');
    }
}
