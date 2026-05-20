<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Model;

class FeatureFlag implements FeatureFlagInterface
{
    protected string $name;
    protected bool $enabled = false;

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
