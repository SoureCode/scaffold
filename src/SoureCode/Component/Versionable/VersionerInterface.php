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
     * Related entities are re-attached at their current state — historical
     * versions of related entities are not restored.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @throws \RuntimeException when the version does not exist
     */
    public function applyVersion(object $entity, int $version): void;
}
