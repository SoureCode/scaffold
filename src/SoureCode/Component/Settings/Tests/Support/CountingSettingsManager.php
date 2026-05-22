<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Support;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;

/**
 * Wraps an InMemorySettingsManager and counts every get() call by key so
 * decorator tests can prove cache layers actually avoid the inner store.
 */
final class CountingSettingsManager implements SettingsManagerInterface
{
    /**
     * @var array<string, int>
     */
    public array $getCalls = [];

    private readonly InMemorySettingsManager $inner;

    /**
     * @param array<string, mixed> $store
     */
    public function __construct(array $store = [])
    {
        $this->inner = new InMemorySettingsManager($store);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCalls[$key] = ($this->getCalls[$key] ?? 0) + 1;

        return $this->inner->get($key, $default);
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        return $this->inner->getString($key, $default);
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->inner->getInt($key, $default);
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        return $this->inner->getBool($key, $default);
    }

    public function getArray(string $key, ?array $default = null): ?array
    {
        return $this->inner->getArray($key, $default);
    }

    public function getMany(array $keys): array
    {
        return $this->inner->getMany($keys);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->inner->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($key);
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }
}
