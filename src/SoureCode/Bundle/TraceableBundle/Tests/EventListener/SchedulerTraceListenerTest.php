<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\EventListener\SchedulerTraceListener;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

final class SchedulerTraceListenerTest extends TestCase
{
    public function testOnPreRunStampsFreshContextWithSchedulerAttributes(): void
    {
        $holder = new TraceContextHolder();
        $listener = new SchedulerTraceListener(new TraceContextFactory(), $holder);

        $trigger = new PeriodicalTrigger('1 minute');
        $messageContext = new MessageContext(
            name: 'nightly-cleanup',
            id: 'nightly-cleanup-1',
            trigger: $trigger,
            triggeredAt: new \DateTimeImmutable('2026-05-21T03:00:00+00:00'),
        );
        $event = new PreRunEvent(new Schedule(), $messageContext, new \stdClass());

        $listener->onPreRun($event);

        $context = $holder->getCurrent();
        self::assertNotNull($context);
        self::assertSame('scheduler', $context->getAttribute('source'));
        self::assertSame(PeriodicalTrigger::class, $context->getAttribute('scheduler.trigger'));
    }

    public function testEachPreRunCreatesAFreshTraceId(): void
    {
        $holder = new TraceContextHolder();
        $listener = new SchedulerTraceListener(new TraceContextFactory(), $holder);

        $trigger = new PeriodicalTrigger('1 minute');
        $messageContext = new MessageContext(
            name: 'nightly-cleanup',
            id: 'nightly-cleanup-1',
            trigger: $trigger,
            triggeredAt: new \DateTimeImmutable('2026-05-21T03:00:00+00:00'),
        );

        $listener->onPreRun(new PreRunEvent(new Schedule(), $messageContext, new \stdClass()));
        $first = $holder->getCurrent()->getId();

        $listener->onPreRun(new PreRunEvent(new Schedule(), $messageContext, new \stdClass()));
        $second = $holder->getCurrent()->getId();

        self::assertFalse($first->equals($second));
    }
}
