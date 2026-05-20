<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Uid\Ulid;

final class ConsoleTraceListener
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly TraceContextHolder $holder,
        private readonly ?string $envVar = 'TRACE_ID',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $incoming = null;

        if ($this->envVar !== null) {
            $raw = getenv($this->envVar);

            if ($raw !== false && $raw !== '') {
                if (Ulid::isValid($raw)) {
                    $incoming = Ulid::fromString($raw);
                } else {
                    $this->logger->warning(
                        'Discarded incoming trace id from ${env}: value "{value}" is not a valid Ulid.',
                        ['env' => $this->envVar, 'value' => $raw],
                    );
                }
            }
        }

        $this->holder->setCurrent($this->factory->create($incoming));
    }
}
