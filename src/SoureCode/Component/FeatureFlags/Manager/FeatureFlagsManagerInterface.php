<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

/**
 * Convenience union of {@see FeatureFlagsReaderInterface} and
 * {@see FeatureFlagsWriterInterface} for managers that expose both halves
 * of the contract.
 */
interface FeatureFlagsManagerInterface extends FeatureFlagsReaderInterface, FeatureFlagsWriterInterface
{
}
