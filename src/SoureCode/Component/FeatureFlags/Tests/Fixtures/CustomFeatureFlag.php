<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Fixtures;

use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

final class CustomFeatureFlag implements FeatureFlagInterface
{
    private string $name;
    private bool $enabled = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
