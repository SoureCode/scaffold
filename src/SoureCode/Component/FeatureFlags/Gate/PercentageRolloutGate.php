<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Gate;

/**
 * Deterministic percentage rollout keyed off `$context['user_id']`.
 *
 * The same user identifier always maps to the same bucket so a rollout at
 * 10 % stays at 10 % across requests — the bucket is computed from
 * crc32($name . ':' . $user_id) mod 100.
 *
 * The flag must be present in the rollout map; flags absent from it return
 * null (the manager falls back to the stored boolean).
 */
final class PercentageRolloutGate implements FeatureGateInterface
{
    /**
     * @param array<string, int> $rollouts flag name → percentage 0-100
     */
    public function __construct(
        private readonly array $rollouts,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        if (!array_key_exists($name, $this->rollouts)) {
            return null;
        }

        $percent = max(0, min(100, $this->rollouts[$name]));

        if ($percent === 0) {
            return false;
        }

        if ($percent === 100) {
            return true;
        }

        $userId = $context['user_id'] ?? null;

        if ($userId === null) {
            return false;
        }

        $bucket = crc32($name . ':' . (string) $userId) % 100;

        return $bucket < $percent;
    }
}
