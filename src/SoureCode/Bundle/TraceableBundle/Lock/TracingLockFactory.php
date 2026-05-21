<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Lock;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Decorates `Symfony\Component\Lock\LockFactory` so every acquire / release
 * is logged with the active trace id. Symfony Lock does not dispatch events
 * of its own; this is the closest integration point.
 *
 * Wire only when the host application has `symfony/lock` installed.
 */
final class TracingLockFactory extends LockFactory
{
    public function __construct(
        private readonly LockFactory $inner,
        private readonly TraceContextHolder $holder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        // intentionally skipping parent::__construct: we proxy everything to $inner.
    }

    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): LockInterface
    {
        return new TracingLock($this->inner->createLock($resource, $ttl, $autoRelease), $resource, $this->holder, $this->logger);
    }

    public function createLockFromKey(\Symfony\Component\Lock\Key $key, ?float $ttl = 300.0, bool $autoRelease = true): LockInterface
    {
        return new TracingLock($this->inner->createLockFromKey($key, $ttl, $autoRelease), (string) $key, $this->holder, $this->logger);
    }
}
