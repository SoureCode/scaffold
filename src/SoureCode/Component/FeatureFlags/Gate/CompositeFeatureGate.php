<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Gate;

/**
 * Walks a list of gates in order and returns the first non-null verdict.
 */
final class CompositeFeatureGate implements FeatureGateInterface
{
    /**
     * @param iterable<FeatureGateInterface> $gates
     */
    public function __construct(
        private readonly iterable $gates,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        foreach ($this->gates as $gate) {
            $verdict = $gate->decide($name, $context);

            if ($verdict !== null) {
                return $verdict;
            }
        }

        return null;
    }
}
