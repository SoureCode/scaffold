<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

/**
 * Read-only surface of the FeatureFlags manager stack. Decorators that only
 * augment reads (e.g. env-override, gates, cache) implement this so their
 * scope is explicit at the type level.
 */
interface FeatureFlagsReaderInterface
{
    public function isEnabled(string $name): bool;

    /**
     * Convenience overload that consults gates configured with the manager
     * (percentage rollout, allow-list, time-window, …). Implementations
     * that do not support gates fall back to {@see isEnabled()}.
     *
     * @param array<string, mixed> $context arbitrary hints ("user_id", "tenant", …)
     */
    public function isEnabledFor(string $name, array $context = []): bool;

    public function has(string $name): bool;

    /**
     * @return Collection<string, FeatureFlagInterface>
     */
    public function all(): Collection;
}
