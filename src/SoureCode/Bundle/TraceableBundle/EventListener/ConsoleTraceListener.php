<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\EventListener;

use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

final class ConsoleTraceListener
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly TraceContextHolder $holder,
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->holder->setCurrent($this->factory->create());
    }
}
