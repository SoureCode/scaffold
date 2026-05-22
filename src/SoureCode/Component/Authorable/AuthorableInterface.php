<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable;

/**
 * Implementing classes MUST back `setUpdatedBy` with a property named
 * `UPDATED_BY_PROPERTY` so the flush listener can recognize a user-set
 * value in the change set. If you need a different property name, use the
 * `#[UpdatedBy]` attribute path instead — it derives the property name
 * from metadata and has no such constraint.
 */
interface AuthorableInterface
{
    public const string UPDATED_BY_PROPERTY = 'updatedBy';

    public function getCreatedBy(): ?object;

    /**
     * @template T of object
     *
     * @param T $author
     */
    public function setCreatedBy(object $author): void;

    public function getUpdatedBy(): ?object;

    /**
     * @template T of object
     *
     * @param T $author
     */
    public function setUpdatedBy(object $author): void;
}
