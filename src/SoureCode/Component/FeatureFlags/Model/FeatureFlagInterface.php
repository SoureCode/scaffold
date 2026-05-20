<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Model;

interface FeatureFlagInterface
{
    public function getName(): string;

    public function setName(string $name): void;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;
}
