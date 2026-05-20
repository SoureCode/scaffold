<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Factory;

use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

interface FeatureFlagFactoryInterface
{
    public function create(string $name): FeatureFlagInterface;
}
