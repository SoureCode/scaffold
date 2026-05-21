<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Model\SettingInterface;

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
