<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Manager\GatedFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\InMemoryFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Tests\Support\FixedVerdictGate;

final class GatedFeatureFlagsManagerTest extends TestCase
{
    public function testGateVerdictWinsOverStoredValue(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['checkout.v2' => false]);
        $manager = new GatedFeatureFlagsManager($inner, new FixedVerdictGate('checkout.v2', true));

        self::assertTrue($manager->isEnabledFor('checkout.v2', ['user_id' => 'x']));
    }

    public function testFallsBackToInnerWhenGateAbstains(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['checkout.v2' => true]);
        $manager = new GatedFeatureFlagsManager($inner, new FixedVerdictGate('checkout.v2', null));

        self::assertTrue($manager->isEnabledFor('checkout.v2'));
    }

    public function testWritesDelegateToInner(): void
    {
        $inner = new InMemoryFeatureFlagsManager();
        $manager = new GatedFeatureFlagsManager($inner, new FixedVerdictGate('checkout.v2', null));

        $manager->enable('checkout.v2');
        self::assertTrue($inner->isEnabled('checkout.v2'));
    }
}
