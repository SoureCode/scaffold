<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable;

interface RemoverInterface
{
    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @throws \LogicException
     */
    public function remove(object $entity, bool $soft = true, bool $flush = true): void;

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @throws \LogicException
     */
    public function restore(object $entity, bool $flush = true): void;

    /**
     * Stamps every entity in turn, then flushes once. Throws on the first
     * entity that lacks a #[DeletedAt] marker; entities stamped before the
     * failure remain modified in the UnitOfWork.
     *
     * @param iterable<object> $entities
     *
     * @throws \LogicException
     */
    public function batchRemove(iterable $entities, bool $soft = true, bool $flush = true): int;

    /**
     * Hard-deletes every soft-deleted row of the given class whose deletedAt
     * is strictly older than the cutoff. Returns the number of rows scheduled.
     *
     * @param class-string $entityClass
     *
     * @throws \LogicException
     */
    public function purge(string $entityClass, \DateTimeInterface $olderThan, bool $flush = true): int;
}
