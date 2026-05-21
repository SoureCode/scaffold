<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\FeatureFlags\Gate\FeatureGateInterface;

/**
 * Consults a {@see FeatureGateInterface} before falling back to the inner
 * manager's stored boolean. The gate is the source of dynamic rollout
 * behaviour (percentage, allow-list, time-window, etc.) and can defer
 * (return null) when it has no opinion on a given flag.
 *
 * Write operations pass straight through to the inner manager.
 */
final class GatedFeatureFlagsManager extends AbstractFeatureFlagsManager
{
    public function __construct(
        private readonly FeatureFlagsManagerInterface $inner,
        private readonly FeatureGateInterface $gate,
    ) {
    }

    public function isEnabled(string $name): bool
    {
        return $this->isEnabledFor($name);
    }

    public function isEnabledFor(string $name, array $context = []): bool
    {
        self::validateName($name);

        $verdict = $this->gate->decide($name, $context);

        if ($verdict !== null) {
            return $verdict;
        }

        return $this->inner->isEnabled($name);
    }

    public function has(string $name): bool
    {
        return $this->inner->has($name);
    }

    public function enable(string $name): void
    {
        $this->inner->enable($name);
    }

    public function disable(string $name): void
    {
        $this->inner->disable($name);
    }

    public function remove(string $name): void
    {
        $this->inner->remove($name);
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }
}
