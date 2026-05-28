<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

interface VersionerInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    public function findHistory(string $className, int|string $entityId): array;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function findByVersion(string $className, int|string $entityId, int $version): ?object;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function findLatestVersion(string $className, int|string $entityId): ?object;

    /**
     * Mutates $entity in place with values from the given historical version.
     * The caller is responsible for flushing; that flush will write a new
     * version row reflecting the revert.
     *
     * Related entities default to being re-attached at their current state.
     * Pass `$cascade = true` to also restore versioned related entities to
     * the version captured alongside this snapshot (via the
     * `<field>_version` column written by VersionableListener). Cascade is
     * single-pass; cycles short-circuit on already-visited entity instances.
     *
     * Pass `$onlyFields` to restore a subset of the versioned properties; the
     * other versioned fields keep their current value. Pass an empty array
     * (the default) to restore all of them.
     *
     * @template T of object
     *
     * @param T $entity
     * @param list<string> $onlyFields property names to restore; empty means "all"
     *
     * @throws \RuntimeException when the version does not exist
     */
    public function applyVersion(
        object $entity,
        int $version,
        array $onlyFields = [],
        bool $cascade = false,
        ?bool $bumpRelations = null,
    ): AppliedVersion;

    /**
     * One-shot override for relationship bump propagation on the next flush.
     * When set, replaces every entity's class-level
     * `#[Versioned(bumpRelations: ...)]` default for that flush only. Resets
     * automatically in `postFlush`, so the next flush starts from the class
     * defaults again.
     */
    public function bumpRelations(bool $value): void;

    /**
     * Returns the per-field before/after pairs between two versions of the
     * same entity. Returns null when either version does not exist.
     *
     * @param class-string $className
     */
    public function diff(string $className, int|string $entityId, int $fromVersion, int $toVersion): ?VersionDiff;

    /**
     * Hard-deletes every version row of the given entity older than the
     * cutoff, keeping at least $keepLast versions per entity intact even
     * when they are older. Returns the number of rows deleted.
     *
     * @param class-string $className
     */
    public function prune(string $className, \DateTimeInterface $olderThan, int $keepLast = 1): int;
}
