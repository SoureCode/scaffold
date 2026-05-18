<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\EventListener;

use Psr\Container\ContainerInterface;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

final class ConsoleTraceListener
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly ContainerInterface $container,
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->container->set(TraceContextInterface::class, $this->factory->create());
    }
}
