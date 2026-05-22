<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * No-op `EventDispatcherInterface` used as the default for managers that
 * accept a dispatcher. Lets injection stay non-nullable so callers do not
 * have to write `?->dispatch(...)` at every call site.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
