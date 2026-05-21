<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Decorator that namespaces every key with a scope prefix before delegating
 * to the inner manager. Useful for per-user / per-tenant / per-role storage
 * without touching the underlying schema:
 *
 *     $userSettings = new ScopedSettingsManager($store, 'user-42.');
 *     $userSettings->set('theme', 'dark');       // → stores "user-42.theme"
 *     $userSettings->get('theme');                // → reads  "user-42.theme"
 *
 * The original key is restored when iterating {@see all()}.
 */
final class ScopedSettingsManager extends AbstractSettingsManager
{
    public function __construct(
        private readonly SettingsManagerInterface $inner,
        private readonly string $scopePrefix,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($this->scopePrefix . $key, $default);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($this->scopePrefix . $key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->inner->set($this->scopePrefix . $key, $value);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($this->scopePrefix . $key);
    }

    public function all(): Collection
    {
        $scoped = new ArrayCollection();

        foreach ($this->inner->all() as $key => $setting) {
            if (!str_starts_with($key, $this->scopePrefix)) {
                continue;
            }

            $scoped->set(substr($key, strlen($this->scopePrefix)), $setting);
        }

        return $scoped;
    }
}
