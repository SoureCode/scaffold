<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

/**
 * Holds the currently active trace context for the running request /
 * console command / messenger envelope. Listeners populate it at the start
 * of a unit of work and clear it at the end.
 *
 * Concurrency: NOT safe across concurrent fibers. The holder uses a single
 * mutable property so two fibers in the same process would clobber each
 * other's trace id. This is acceptable in a sync HTTP worker — one fiber
 * per request — and intentional given Symfony's current execution model.
 * Once Symfony fiber-based scheduling lands, the holder will need a
 * fiber-local store (e.g. `\Fiber::getCurrent()`-keyed map) to stay safe.
 */
final class TraceContextHolder
{
    private ?TraceContextInterface $current = null;

    public function setCurrent(?TraceContextInterface $context): void
    {
        $this->current = $context;
    }

    public function getCurrent(): ?TraceContextInterface
    {
        return $this->current;
    }
}
