<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Gate;

/**
 * Resolves whether a flag is enabled for a given context (typically a user
 * identifier or a tenant). Implementations decide the rollout shape —
 * percentage, allow-list, time-window, etc.
 *
 * Returning null means "no opinion"; the manager continues to consult the
 * next gate or falls back to the stored boolean.
 */
interface FeatureGateInterface
{
    /**
     * @param array<string, mixed> $context arbitrary key-value hints
     *                                      ("user_id", "tenant", …)
     */
    public function decide(string $name, array $context = []): ?bool;
}
