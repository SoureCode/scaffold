<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

/**
 * Write surface of the FeatureFlags manager stack. Persistence-only
 * implementations (DoctrineFeatureFlagsManager) and decorators that need
 * to intercept writes (audit, cache invalidation) implement this.
 */
interface FeatureFlagsWriterInterface
{
    public function enable(string $name): void;

    public function disable(string $name): void;

    public function remove(string $name): void;
}
