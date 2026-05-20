<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Factory;

use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

final class FeatureFlagFactory implements FeatureFlagFactoryInterface
{
    /**
     * @param class-string<FeatureFlagInterface> $entityClass
     */
    public function __construct(
        private readonly string $entityClass = FeatureFlag::class,
    ) {
        if (!is_a($this->entityClass, FeatureFlagInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'FeatureFlagFactory entity class "%s" must implement %s.',
                $this->entityClass,
                FeatureFlagInterface::class,
            ));
        }
    }

    public function create(string $name): FeatureFlagInterface
    {
        $flag = new ($this->entityClass)();
        $flag->setName($name);

        return $flag;
    }
}
