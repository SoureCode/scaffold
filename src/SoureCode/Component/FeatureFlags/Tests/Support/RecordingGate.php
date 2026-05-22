<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Support;

use SoureCode\Component\FeatureFlags\Gate\FeatureGateInterface;

/**
 * Appends its label to a shared log when consulted, then returns the
 * configured verdict. Used to prove a composite gate evaluates its
 * children in registration order and stops at the first decision.
 */
final class RecordingGate implements FeatureGateInterface
{
    /**
     * @param \ArrayObject<int, string> $log
     */
    public function __construct(
        private readonly string $label,
        private readonly ?bool $verdict,
        private readonly \ArrayObject $log,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        $this->log[] = $this->label;

        return $this->verdict;
    }
}
