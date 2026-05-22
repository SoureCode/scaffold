<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Lock;

use Psr\Log\LoggerInterface;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Wraps a `Symfony\Component\Lock\SharedLockInterface` and tags each acquire /
 * release with the current trace id so contention shows up in the log
 * stream alongside the requesting trace.
 *
 * Logging asymmetry: only `acquire()` and `release()` emit log records.
 * `acquireRead()`, `refresh()`, `isAcquired()`, `getRemainingLifetime()`,
 * and `isExpired()` proxy silently — they do not change ownership and
 * would otherwise drown the trace stream with noise during a single
 * critical section. If you need them logged, decorate this class further.
 */
final class TracingLock implements SharedLockInterface
{
    public function __construct(
        private readonly SharedLockInterface $inner,
        private readonly string $resource,
        private readonly TraceContextHolder $holder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function acquire(bool $blocking = false): bool
    {
        $start = microtime(true);
        $acquired = $this->inner->acquire($blocking);
        $elapsedMs = (int) ((microtime(true) - $start) * 1000);

        $this->logger->info(
            'lock.acquire {resource} → {result} in {elapsed_ms} ms (trace={trace_id})',
            [
                'resource' => $this->resource,
                'result' => $acquired ? 'ok' : 'failed',
                'elapsed_ms' => $elapsedMs,
                'trace_id' => $this->traceId(),
            ],
        );

        return $acquired;
    }

    public function acquireRead(bool $blocking = false): bool
    {
        return $this->inner->acquireRead($blocking);
    }

    public function refresh(?float $ttl = null): void
    {
        $this->inner->refresh($ttl);
    }

    public function isAcquired(): bool
    {
        return $this->inner->isAcquired();
    }

    public function release(): void
    {
        $this->inner->release();
        $this->logger->info(
            'lock.release {resource} (trace={trace_id})',
            [
                'resource' => $this->resource,
                'trace_id' => $this->traceId(),
            ],
        );
    }

    public function isExpired(): bool
    {
        return $this->inner->isExpired();
    }

    public function getRemainingLifetime(): ?float
    {
        return $this->inner->getRemainingLifetime();
    }

    private function traceId(): ?string
    {
        $context = $this->holder->getCurrent();

        return $context === null ? null : (string) $context->getId();
    }
}
