<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Model\SettingInterface;

interface SettingsManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /**
     * @return Collection<string, SettingInterface>
     */
    public function all(): Collection;
}
