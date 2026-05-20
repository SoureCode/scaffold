<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use SoureCode\Component\FeatureFlags\Manager\AbstractFeatureFlagsManager;

final class NameValidationProbe extends AbstractFeatureFlagsManager
{
    public static function probe(string $name): void
    {
        self::validateName($name);
    }

    public function isEnabled(string $name): bool
    {
        return false;
    }

    public function has(string $name): bool
    {
        return false;
    }

    public function enable(string $name): void
    {
    }

    public function disable(string $name): void
    {
    }

    public function remove(string $name): void
    {
    }

    public function all(): Collection
    {
        return new ArrayCollection();
    }
}
