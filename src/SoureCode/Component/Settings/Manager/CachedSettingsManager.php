<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Wraps another manager with a PSR-6 cache. Reads consult the cache first;
 * writes invalidate the affected key. Useful in front of `DoctrineSettingsManager`
 * to avoid hitting the DB on every `setting()` lookup in templates.
 *
 * Tuning: cache items are stored under "<prefix><key>" and never expire on
 * their own — only on write — so a manual purge by key is necessary if you
 * mutate the underlying store outside this manager.
 */
final class CachedSettingsManager extends AbstractSettingsManager
{
    public function __construct(
        private readonly SettingsManagerInterface $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $prefix = 'sourecode.setting.',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        self::validateKey($key);

        $item = $this->cache->getItem($this->prefix . $key);

        if ($item->isHit()) {
            return $item->get() ?? $default;
        }

        $value = $this->inner->get($key);
        $item->set($value);
        $this->cache->save($item);

        return $value ?? $default;
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->inner->set($key, $value);
        $this->cache->deleteItem($this->prefix . $key);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($key);
        $this->cache->deleteItem($this->prefix . $key);
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }
}
