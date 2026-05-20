<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;

final class InMemorySettingsManagerTest extends TestCase
{
    public function testGetReturnsDefaultWhenKeyIsMissing(): void
    {
        $manager = new InMemorySettingsManager();

        self::assertSame('fallback', $manager->get('missing', 'fallback'));
    }

    public function testNullStoredValueIsReturnedAsNullEvenWhenDefaultGiven(): void
    {
        $manager = new InMemorySettingsManager(['x' => null]);

        self::assertNull($manager->get('x', 'fallback'));
        self::assertTrue($manager->has('x'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $manager = new InMemorySettingsManager();

        $manager->set('a', 1);
        $manager->set('b', ['nested' => true]);

        self::assertSame(1, $manager->get('a'));
        self::assertSame(['nested' => true], $manager->get('b'));
    }

    public function testRemoveDeletesEntry(): void
    {
        $manager = new InMemorySettingsManager(['a' => 1]);

        $manager->remove('a');

        self::assertFalse($manager->has('a'));
    }

    public function testAllReturnsEveryEntry(): void
    {
        $manager = new InMemorySettingsManager(['a' => 1, 'b' => 2]);

        $collection = $manager->all();

        self::assertCount(2, $collection);
        self::assertSame(1, $collection->get('a')->getValue());
        self::assertSame(2, $collection->get('b')->getValue());
    }

    public function testInvalidKeyOnGetIsRejected(): void
    {
        $manager = new InMemorySettingsManager();

        $this->expectException(\InvalidArgumentException::class);

        $manager->get('Invalid Key');
    }

    public function testInvalidSeedKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new InMemorySettingsManager(['Invalid Key' => 1]);
    }
}
