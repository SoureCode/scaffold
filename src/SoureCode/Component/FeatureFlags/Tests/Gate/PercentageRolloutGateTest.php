<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Gate;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Gate\PercentageRolloutGate;

final class PercentageRolloutGateTest extends TestCase
{
    public function testZeroPercentDeniesEveryone(): void
    {
        $gate = new PercentageRolloutGate(['checkout.v2' => 0]);

        self::assertFalse($gate->decide('checkout.v2', ['user_id' => 'alice']));
    }

    public function testHundredPercentAllowsEveryone(): void
    {
        $gate = new PercentageRolloutGate(['checkout.v2' => 100]);

        self::assertTrue($gate->decide('checkout.v2', ['user_id' => 'alice']));
    }

    public function testAbsentFlagReturnsNullSoManagerFallsBack(): void
    {
        $gate = new PercentageRolloutGate(['checkout.v2' => 50]);

        self::assertNull($gate->decide('other.flag', ['user_id' => 'alice']));
    }

    public function testWithoutUserIdAbstainsSoManagerFallsBack(): void
    {
        $gate = new PercentageRolloutGate(['checkout.v2' => 50]);

        self::assertNull($gate->decide('checkout.v2'));
    }

    public function testDecisionIsDeterministicPerUserPerFlag(): void
    {
        $gate = new PercentageRolloutGate(['checkout.v2' => 50]);

        $first = $gate->decide('checkout.v2', ['user_id' => 'user-42']);
        $second = $gate->decide('checkout.v2', ['user_id' => 'user-42']);

        self::assertSame($first, $second);
    }
}
