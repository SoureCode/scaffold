<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Event\SettingChangedEvent;
use SoureCode\Component\Settings\Event\SettingRemovedEvent;
use SoureCode\Component\Settings\Manager\AuditedSettingsManager;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Tests\Support\RecordingEventDispatcher;

final class AuditedSettingsManagerTest extends TestCase
{
    public function testSetDispatchesChangedEventWithPreviousAndNewValue(): void
    {
        $inner = new InMemorySettingsManager(['site.title' => 'Old']);
        $dispatcher = new RecordingEventDispatcher();
        $manager = new AuditedSettingsManager($inner, $dispatcher);

        $manager->set('site.title', 'New');

        self::assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        self::assertInstanceOf(SettingChangedEvent::class, $event);
        self::assertSame('site.title', $event->key);
        self::assertSame('Old', $event->previousValue);
        self::assertSame('New', $event->newValue);
        self::assertSame('New', $inner->get('site.title'));
    }

    public function testSetOnMissingKeyDispatchesWithNullPrevious(): void
    {
        $inner = new InMemorySettingsManager();
        $dispatcher = new RecordingEventDispatcher();
        $manager = new AuditedSettingsManager($inner, $dispatcher);

        $manager->set('site.title', 'Fresh');

        self::assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        self::assertInstanceOf(SettingChangedEvent::class, $event);
        self::assertNull($event->previousValue);
        self::assertSame('Fresh', $event->newValue);
    }

    public function testRemoveDispatchesRemovedEventWithPreviousValue(): void
    {
        $inner = new InMemorySettingsManager(['site.title' => 'Doomed']);
        $dispatcher = new RecordingEventDispatcher();
        $manager = new AuditedSettingsManager($inner, $dispatcher);

        $manager->remove('site.title');

        self::assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        self::assertInstanceOf(SettingRemovedEvent::class, $event);
        self::assertSame('site.title', $event->key);
        self::assertSame('Doomed', $event->previousValue);
        self::assertFalse($inner->has('site.title'));
    }

    public function testReadsPassThroughWithoutDispatching(): void
    {
        $inner = new InMemorySettingsManager(['site.title' => 'Hello']);
        $dispatcher = new RecordingEventDispatcher();
        $manager = new AuditedSettingsManager($inner, $dispatcher);

        self::assertSame('Hello', $manager->get('site.title'));
        self::assertTrue($manager->has('site.title'));
        self::assertSame(['site.title'], $manager->all()->getKeys());
        self::assertSame([], $dispatcher->events);
    }
}
