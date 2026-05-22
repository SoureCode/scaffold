<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable;

/**
 * Optional collaborator for `Remover` so behaviors that own additional
 * deletion markers (e.g. Authorable's `#[DeletedBy]`) can stamp and clear
 * them without `Remover` taking a direct dependency on those behaviors.
 *
 * Implementations are wired by the originating behavior's bundle and
 * injected as a tagged-services iterable.
 */
interface DeletionMarkerProviderInterface
{
    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function fillDeletionMarkers(object $entity): void;

    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function clearDeletionMarkers(object $entity): void;
}
