<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Messenger;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class TraceContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly ContainerInterface $container,
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

            $this->container->set(TraceContextInterface::class, $this->factory->create($traceStamp?->id));

            return $stack->next()->handle($envelope, $stack);
        }

        if ($envelope->last(TraceStamp::class) === null && $this->container->has(TraceContextInterface::class)) {
            /** @var TraceContextInterface $context */
            $context = $this->container->get(TraceContextInterface::class);
            $envelope = $envelope->with(new TraceStamp($context->getId()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
