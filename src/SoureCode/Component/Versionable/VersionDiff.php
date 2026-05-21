<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

/**
 * Per-field before/after pair returned by {@see VersionerInterface::diff()}.
 *
 * `before` and `after` are the database-converted PHP values; relations are
 * rehydrated through the same lookup the Versioner uses for restore, so
 * comparisons are object-identity safe inside one EntityManager.
 */
final class VersionDiff
{
    /**
     * @param array<string, array{before: mixed, after: mixed}> $changes
     */
    public function __construct(
        public readonly int $fromVersion,
        public readonly int $toVersion,
        public readonly array $changes,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    /**
     * @return list<string>
     */
    public function changedFieldNames(): array
    {
        return array_keys($this->changes);
    }
}
