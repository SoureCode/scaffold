<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\EventListener;

use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Scheduler\Event\PreRunEvent;

/**
 * Stamps a fresh trace context when a scheduled task starts so its log
 * lines have a correlation id. Wire only when symfony/scheduler is
 * present in the project.
 */
final class SchedulerTraceListener
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly TraceContextHolder $holder,
    ) {
    }

    public function onPreRun(PreRunEvent $event): void
    {
        $message = $event->getMessageContext();
        $attributes = [
            'source' => 'scheduler',
            'scheduler.trigger' => $message->trigger::class,
        ];

        $this->holder->setCurrent($this->factory->create(null, $attributes));
    }
}
