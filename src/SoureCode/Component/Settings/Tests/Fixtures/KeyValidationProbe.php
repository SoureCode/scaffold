<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Manager\AbstractSettingsManager;

final class KeyValidationProbe extends AbstractSettingsManager
{
    public static function probe(string $key): void
    {
        self::validateKey($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function set(string $key, mixed $value): void
    {
    }

    public function remove(string $key): void
    {
    }

    public function all(): Collection
    {
        return new ArrayCollection();
    }
}
