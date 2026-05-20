<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Model;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;

final class FeatureFlagTest extends TestCase
{
    public function testNameRoundTrip(): void
    {
        $flag = new FeatureFlag();
        $flag->setName('beta');

        self::assertSame('beta', $flag->getName());
    }

    public function testEnabledDefaultsToFalse(): void
    {
        $flag = new FeatureFlag();

        self::assertFalse($flag->isEnabled());
    }

    public function testEnabledRoundTrip(): void
    {
        $flag = new FeatureFlag();

        $flag->setEnabled(true);
        self::assertTrue($flag->isEnabled());

        $flag->setEnabled(false);
        self::assertFalse($flag->isEnabled());
    }
}
