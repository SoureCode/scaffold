<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Messenger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class TraceContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly TraceContextHolder $holder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) !== null) {
            $traceStamp = $envelope->last(TraceStamp::class);

            if ($traceStamp === null) {
                $this->logger->warning(
                    'Traceable: received message of type {message} without TraceStamp; a fresh trace id was generated.',
                    ['message' => $envelope->getMessage()::class],
                );
            }

            $this->holder->setCurrent($this->factory->create($traceStamp?->id));

            return $stack->next()->handle($envelope, $stack);
        }

        if ($envelope->last(TraceStamp::class) === null) {
            $current = $this->holder->getCurrent();

            if ($current !== null) {
                $envelope = $envelope->with(new TraceStamp($current->getId()));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
