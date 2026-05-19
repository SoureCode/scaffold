<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Uid\Ulid;

final class HttpTraceListener
{
    public function __construct(
        private readonly TraceContextFactory $factory,
        private readonly TraceContextHolder $holder,
        private readonly ?string $requestHeader = 'X-Request-Id',
        private readonly ?string $responseHeader = 'X-Request-Id',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $incoming = null;

        if ($this->requestHeader !== null) {
            $raw = $event->getRequest()->headers->get($this->requestHeader);

            if ($raw !== null) {
                if (Ulid::isValid($raw)) {
                    $incoming = Ulid::fromString($raw);
                } else {
                    $this->logger->warning(
                        'Discarded incoming trace id from {header}: value "{value}" is not a valid Ulid.',
                        ['header' => $this->requestHeader, 'value' => $raw],
                    );
                }
            }
        }

        $this->holder->setCurrent($this->factory->create($incoming));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->responseHeader === null) {
            return;
        }

        $context = $this->holder->getCurrent();

        if ($context === null) {
            return;
        }

        $event->getResponse()->headers->set($this->responseHeader, (string) $context->getId());
    }
}
