<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Manager\InMemoryFeatureFlagsManager;

final class InMemoryFeatureFlagsManagerTest extends TestCase
{
    public function testMissingFlagIsNotEnabled(): void
    {
        $manager = new InMemoryFeatureFlagsManager();

        self::assertFalse($manager->isEnabled('beta'));
        self::assertFalse($manager->has('beta'));
    }

    public function testEnableCreatesFlagAndTurnsItOn(): void
    {
        $manager = new InMemoryFeatureFlagsManager();

        $manager->enable('beta');

        self::assertTrue($manager->isEnabled('beta'));
        self::assertTrue($manager->has('beta'));
    }

    public function testDisableCreatesFlagAndTurnsItOff(): void
    {
        $manager = new InMemoryFeatureFlagsManager();

        $manager->disable('beta');

        self::assertFalse($manager->isEnabled('beta'));
        self::assertTrue($manager->has('beta'));
    }

    public function testEnableThenDisableFlipsTheState(): void
    {
        $manager = new InMemoryFeatureFlagsManager();

        $manager->enable('beta');
        $manager->disable('beta');

        self::assertFalse($manager->isEnabled('beta'));
        self::assertTrue($manager->has('beta'));
    }

    public function testRemoveDeletesFlag(): void
    {
        $manager = new InMemoryFeatureFlagsManager(['beta' => true]);

        $manager->remove('beta');

        self::assertFalse($manager->has('beta'));
        self::assertFalse($manager->isEnabled('beta'));
    }

    public function testAllReturnsRichCollection(): void
    {
        $manager = new InMemoryFeatureFlagsManager(['a' => true, 'b' => false]);

        $collection = $manager->all();

        self::assertCount(2, $collection);
        self::assertTrue($collection->get('a')->isEnabled());
        self::assertFalse($collection->get('b')->isEnabled());
    }

    public function testInvalidNameIsRejected(): void
    {
        $manager = new InMemoryFeatureFlagsManager();

        $this->expectException(\InvalidArgumentException::class);

        $manager->isEnabled('Invalid Name');
    }

    public function testInvalidSeedNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new InMemoryFeatureFlagsManager(['Invalid Name' => true]);
    }
}
