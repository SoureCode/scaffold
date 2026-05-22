<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Manager\CachedSettingsManager;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Tests\Support\CountingSettingsManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedSettingsManagerTest extends TestCase
{
    public function testFirstReadHitsInnerAndPopulatesCache(): void
    {
        $inner = new CountingSettingsManager(['site.title' => 'Hello']);
        $manager = new CachedSettingsManager($inner, new ArrayAdapter());

        self::assertSame('Hello', $manager->get('site.title'));
        self::assertSame(1, $inner->getCalls['site.title']);
    }

    public function testSecondReadIsServedFromCache(): void
    {
        $inner = new CountingSettingsManager(['site.title' => 'Hello']);
        $manager = new CachedSettingsManager($inner, new ArrayAdapter());

        $manager->get('site.title');
        $manager->get('site.title');

        self::assertSame(1, $inner->getCalls['site.title'], 'inner consulted only once across two reads');
    }

    public function testSetInvalidatesTheCachedKey(): void
    {
        $inner = new CountingSettingsManager(['site.title' => 'Hello']);
        $manager = new CachedSettingsManager($inner, new ArrayAdapter());

        $manager->get('site.title');
        $manager->set('site.title', 'Updated');
        self::assertSame('Updated', $manager->get('site.title'));
        self::assertSame(2, $inner->getCalls['site.title'], 'invalidate forces a second inner read');
    }

    public function testRemoveInvalidatesTheCachedKey(): void
    {
        $inner = new CountingSettingsManager(['site.title' => 'Hello']);
        $manager = new CachedSettingsManager($inner, new ArrayAdapter());

        $manager->get('site.title');
        $manager->remove('site.title');
        self::assertNull($manager->get('site.title'));
    }

    public function testDefaultIsHonouredWhenInnerReturnsNull(): void
    {
        $manager = new CachedSettingsManager(new InMemorySettingsManager(), new ArrayAdapter());

        self::assertSame('fallback', $manager->get('missing.key', 'fallback'));
    }
}
