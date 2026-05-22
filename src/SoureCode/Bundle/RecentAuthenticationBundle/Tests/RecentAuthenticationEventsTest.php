<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthClearedEvent;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthMarkedEvent;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Tests\Support\RecordingEventDispatcher;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class RecentAuthenticationEventsTest extends TestCase
{
    public function testMarkDispatchesMarkedEventWithTimestamp(): void
    {
        [$service, $clock, $dispatcher] = $this->makeServiceWithSession('2026-05-21 12:00:00');

        $service->mark();

        self::assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        self::assertInstanceOf(RecentAuthMarkedEvent::class, $event);
        self::assertSame($clock->now()->getTimestamp(), $event->atTimestamp);
    }

    public function testClearDispatchesClearedEvent(): void
    {
        [$service, , $dispatcher] = $this->makeServiceWithSession('2026-05-21 12:00:00');
        $service->mark();
        $dispatcher->events = [];

        $service->clear();

        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(RecentAuthClearedEvent::class, $dispatcher->events[0]);
    }

    public function testTtlExpiryDispatchesClearedEvent(): void
    {
        [$service, $clock, $dispatcher] = $this->makeServiceWithSession('2026-05-21 12:00:00');
        $service->mark();
        $dispatcher->events = [];

        $clock->modify('+901 seconds');
        self::assertFalse($service->isActive());

        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(RecentAuthClearedEvent::class, $dispatcher->events[0]);
    }

    public function testPerAttributeTtlMissDoesNotDispatchClearedEvent(): void
    {
        [$service, $clock, $dispatcher] = $this->makeServiceWithSession('2026-05-21 12:00:00');
        $service->mark();
        $dispatcher->events = [];

        $clock->modify('+120 seconds');
        self::assertFalse($service->isActive(60), 'tight per-attribute ttl rejects');

        self::assertSame([], $dispatcher->events, 'per-attribute miss must NOT wipe the underlying session timestamp');
    }

    /**
     * @return array{0: RecentAuthentication, 1: MockClock, 2: RecordingEventDispatcher}
     */
    private function makeServiceWithSession(string $now): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $clock = new MockClock($now);
        $dispatcher = new RecordingEventDispatcher();

        return [new RecentAuthentication($requestStack, $clock, 900, $dispatcher), $clock, $dispatcher];
    }
}
