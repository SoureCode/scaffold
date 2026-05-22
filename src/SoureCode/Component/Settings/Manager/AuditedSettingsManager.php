<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Component\Settings\Event\SettingChangedEvent;
use SoureCode\Component\Settings\Event\SettingRemovedEvent;

/**
 * Decorator that dispatches a {@see SettingChangedEvent} on every `set()`
 * (previous + new value) and a {@see SettingRemovedEvent} on every `remove()`
 * (previous value). Subscribers can write to an audit log, fire webhooks,
 * or invalidate downstream caches.
 *
 * Reads pass through. The decorator does not store anything on its own —
 * audit persistence is the subscriber's job, in line with the project rule
 * that audit trails are host-application concerns.
 *
 * Concurrency note: the `previous` value carried by `SettingChangedEvent` /
 * `SettingRemovedEvent` is best-effort. The manager interface has no
 * atomic compare-and-swap, so a concurrent writer landing between the
 * `previous` read and our `set`/`remove` will silently make `previous`
 * stale. Subscribers that need a strict before/after audit must re-read
 * the authoritative store inside a transaction.
 */
final class AuditedSettingsManager extends AbstractSettingsManager
{
    public function __construct(
        private readonly SettingsManagerInterface $inner,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        $previous = $this->inner->get($key);
        $this->inner->set($key, $value);
        $this->eventDispatcher->dispatch(new SettingChangedEvent($key, $previous, $value));
    }

    public function remove(string $key): void
    {
        $previous = $this->inner->get($key);
        $this->inner->remove($key);
        $this->eventDispatcher->dispatch(new SettingRemovedEvent($key, $previous));
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }
}
