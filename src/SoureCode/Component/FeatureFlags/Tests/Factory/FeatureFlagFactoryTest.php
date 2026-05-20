<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Factory;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;

final class FeatureFlagFactoryTest extends TestCase
{
    public function testCreateProducesFlagInstanceWithName(): void
    {
        $factory = new FeatureFlagFactory();
        $flag = $factory->create('beta');

        self::assertInstanceOf(FeatureFlag::class, $flag);
        self::assertSame('beta', $flag->getName());
        self::assertFalse($flag->isEnabled());
    }

    public function testCreateRespectsConfiguredEntityClass(): void
    {
        $factory = new FeatureFlagFactory(CustomFeatureFlag::class);
        $flag = $factory->create('alpha');

        self::assertInstanceOf(CustomFeatureFlag::class, $flag);
        self::assertSame('alpha', $flag->getName());
    }

    public function testConstructorRejectsClassNotImplementingFeatureFlagInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FeatureFlagFactory(\stdClass::class);
    }
}
