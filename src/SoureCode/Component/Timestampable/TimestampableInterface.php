<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable;

/**
 * Optional fallback contract for attribute-less entities. Covers only
 * created/updated — `#[ChangedAt]` is a domain-specific watcher with no
 * sensible generic accessor pair, and `#[DeletedAt]` is a marker-only
 * column owned by `Remover`.
 */
interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void;
}
