<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Gate;

use Psr\Clock\ClockInterface;

/**
 * Enables a flag only during a fixed wall-clock window. Useful for kill-
 * switches that automatically expire, or for flags that only flip on at a
 * given time.
 *
 * Endpoints are inclusive of `from`, exclusive of `until`. Either endpoint
 * may be null to mean "open".
 *
 * @phpstan-type Window array{from?: ?\DateTimeInterface, until?: ?\DateTimeInterface}
 */
final class TimeWindowGate implements FeatureGateInterface
{
    /**
     * @param array<string, Window> $windows
     */
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly array $windows,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        if (!array_key_exists($name, $this->windows)) {
            return null;
        }

        $window = $this->windows[$name];
        $now = $this->clock->now();

        $from = $window['from'] ?? null;
        $until = $window['until'] ?? null;

        if ($from !== null && $now < $from) {
            return false;
        }

        if ($until !== null && $now >= $until) {
            return false;
        }

        return true;
    }
}
