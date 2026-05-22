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

    private const string ALL_KEY = '__all__';

    public function get(string $key, mixed $default = null): mixed
    {
        self::validateKey($key);

        $item = $this->cache->getItem($this->prefix . $key);

        if ($item->isHit()) {
            // Cache an envelope so a setting that legitimately stores
            // null is observable through the cache instead of being
            // mistaken for "missing".
            $cached = $item->get();

            return $cached['exists'] ? $cached['value'] : $default;
        }

        $exists = $this->inner->has($key);
        $value = $exists ? $this->inner->get($key) : null;

        $item->set(['exists' => $exists, 'value' => $value]);
        $this->cache->save($item);

        return $exists ? $value : $default;
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->inner->set($key, $value);
        $this->cache->deleteItem($this->prefix . $key);
        $this->cache->deleteItem($this->prefix . self::ALL_KEY);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($key);
        $this->cache->deleteItem($this->prefix . $key);
        $this->cache->deleteItem($this->prefix . self::ALL_KEY);
    }

    public function all(): Collection
    {
        // all() used to go straight to the inner store, giving reads via
        // get() and reads via all() different latency profiles. Cache the
        // full collection under a reserved sub-key and invalidate it on
        // every write so both surfaces share the same cache layer.
        $item = $this->cache->getItem($this->prefix . self::ALL_KEY);

        if ($item->isHit()) {
            return $item->get();
        }

        $collection = $this->inner->all();
        $item->set($collection);
        $this->cache->save($item);

        return $collection;
    }
}
