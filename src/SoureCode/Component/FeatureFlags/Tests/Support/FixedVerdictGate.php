<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Support;

use SoureCode\Component\FeatureFlags\Gate\FeatureGateInterface;

/**
 * Returns the configured verdict when the flag name matches the one this
 * gate was built for, otherwise abstains.
 */
final class FixedVerdictGate implements FeatureGateInterface
{
    public function __construct(
        private readonly string $expectedName,
        private readonly ?bool $verdict,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        return $name === $this->expectedName ? $this->verdict : null;
    }
}
