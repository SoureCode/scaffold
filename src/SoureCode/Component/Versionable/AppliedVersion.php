<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

/**
 * Returned by {@see VersionerInterface::applyVersion()} so callers can tell
 * which fields actually changed when reverting to a historical state.
 */
final class AppliedVersion
{
    /**
     * @param list<string> $changedFields property names that differed before vs. after the restore
     */
    public function __construct(
        public readonly int $version,
        public readonly array $changedFields,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->changedFields !== [];
    }
}
