<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Model\SettingInterface;

/**
 * Read/write surface for the settings store. Decorators implement this
 * to add cross-cutting behaviour (cache, encryption, audit, validation).
 *
 * Decorator ordering matters and is the host's responsibility. Two rules
 * the toolkit treats as load-bearing:
 *   - `CachedSettingsManager` MUST wrap `EncryptingSettingsManager`, not
 *     the other way around. Caching plaintext defeats the point of
 *     encryption-at-rest, and the cache invalidates per-key so a wrong
 *     ordering would persist encrypted blobs in memory.
 *   - `AuditedSettingsManager` MUST sit above the persistence layer so
 *     the events it emits reflect the canonical store, not a cached
 *     copy.
 *
 * The shipped Symfony bundle wires the stack as
 * `Cached → Audited → Encrypting → Doctrine`; custom assemblies must
 * preserve those two ordering rules.
 */
interface SettingsManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function getString(string $key, ?string $default = null): ?string;

    public function getInt(string $key, ?int $default = null): ?int;

    public function getBool(string $key, ?bool $default = null): ?bool;

    /**
     * @template T of array
     *
     * @param T|null $default
     *
     * @return T|null
     */
    public function getArray(string $key, ?array $default = null): ?array;

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /**
     * @return Collection<string, SettingInterface>
     */
    public function all(): Collection;
}
