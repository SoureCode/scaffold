<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle;

/**
 * Optional fallback contract for attribute-less entities. Covers only
 * created/updated — `#[ChangedAt]` is a domain-specific watcher with no
 * sensible generic accessor pair, and `#[DeletedAt]` is a marker-only
 * column owned by `Remover`.
 *
 * Implementing classes MUST back `setUpdatedAt` with a property named
 * `UPDATED_AT_PROPERTY` so the flush listener can recognize a user-set
 * value in the change set. If you need a different property name, use the
 * `#[UpdatedAt]` attribute path instead — it derives the property name
 * from metadata and has no such constraint.
 */
interface TimestampableInterface
{
    public const string UPDATED_AT_PROPERTY = 'updatedAt';

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void;
}
