<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

interface FeatureFlagsManagerInterface
{
    public function isEnabled(string $name): bool;

    public function has(string $name): bool;

    public function enable(string $name): void;

    public function disable(string $name): void;

    public function remove(string $name): void;

    /**
     * @return Collection<string, FeatureFlagInterface>
     */
    public function all(): Collection;
}
