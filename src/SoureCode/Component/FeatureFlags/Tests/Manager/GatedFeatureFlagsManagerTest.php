<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Gate\FeatureGateInterface;
use SoureCode\Component\FeatureFlags\Manager\GatedFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\InMemoryFeatureFlagsManager;

final class GatedFeatureFlagsManagerTest extends TestCase
{
    public function testGateVerdictWinsOverStoredValue(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['checkout.v2' => false]);
        $gate = new class implements FeatureGateInterface {
            public function decide(string $name, array $context = []): ?bool
            {
                return $name === 'checkout.v2' ? true : null;
            }
        };
        $manager = new GatedFeatureFlagsManager($inner, $gate);

        self::assertTrue($manager->isEnabledFor('checkout.v2', ['user_id' => 'x']));
    }

    public function testFallsBackToInnerWhenGateAbstains(): void
    {
        $inner = new InMemoryFeatureFlagsManager(['checkout.v2' => true]);
        $gate = new class implements FeatureGateInterface {
            public function decide(string $name, array $context = []): ?bool
            {
                return null;
            }
        };
        $manager = new GatedFeatureFlagsManager($inner, $gate);

        self::assertTrue($manager->isEnabledFor('checkout.v2'));
    }

    public function testWritesDelegateToInner(): void
    {
        $inner = new InMemoryFeatureFlagsManager();
        $gate = new class implements FeatureGateInterface {
            public function decide(string $name, array $context = []): ?bool
            {
                return null;
            }
        };
        $manager = new GatedFeatureFlagsManager($inner, $gate);

        $manager->enable('checkout.v2');
        self::assertTrue($inner->isEnabled('checkout.v2'));
    }
}
