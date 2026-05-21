<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Manager\ScopedSettingsManager;

final class ScopedSettingsManagerTest extends TestCase
{
    public function testWritesAndReadsAreNamespaced(): void
    {
        $store = new InMemorySettingsManager();
        $scoped = new ScopedSettingsManager($store, 'user-42.');

        $scoped->set('theme', 'dark');

        self::assertSame('dark', $scoped->get('theme'));
        self::assertSame('dark', $store->get('user-42.theme'));
        self::assertNull($store->get('theme'));
    }

    public function testAllStripsTheScopeFromReturnedKeys(): void
    {
        $store = new InMemorySettingsManager();
        $alice = new ScopedSettingsManager($store, 'user.alice.');
        $bob = new ScopedSettingsManager($store, 'user.bob.');

        $alice->set('theme', 'dark');
        $bob->set('theme', 'light');

        self::assertSame(['theme'], $alice->all()->getKeys());
        self::assertSame('dark', $alice->all()->get('theme')->getValue());
        self::assertSame('light', $bob->all()->get('theme')->getValue());
    }
}
