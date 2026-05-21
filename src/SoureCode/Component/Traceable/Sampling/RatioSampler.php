<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Sampling;

/**
 * Probabilistic sampler. `random_int` is fine for this — the verdict does
 * not need to be cryptographically secure, just uniformly distributed.
 */
final class RatioSampler implements SamplerInterface
{
    public function __construct(
        private readonly float $ratio,
    ) {
        if ($ratio < 0.0 || $ratio > 1.0) {
            throw new \InvalidArgumentException(\sprintf('ratio must be between 0 and 1, got %s.', $ratio));
        }
    }

    public function shouldSample(array $context = []): bool
    {
        if ($this->ratio <= 0.0) {
            return false;
        }

        if ($this->ratio >= 1.0) {
            return true;
        }

        return (random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX) < $this->ratio;
    }
}
