<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

abstract class AbstractFeatureFlagsManager implements FeatureFlagsManagerInterface
{
    protected const string NAME_PATTERN = '/^[a-z0-9][a-z0-9._-]*$/';

    /**
     * @param array<string, mixed> $context
     */
    public function isEnabledFor(string $name, array $context = []): bool
    {
        return $this->isEnabled($name);
    }

    protected static function validateName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid feature flag name "%s": must match %s.',
                $name,
                self::NAME_PATTERN,
            ));
        }
    }
}
