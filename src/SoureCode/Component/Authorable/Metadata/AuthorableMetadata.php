<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;

final class AuthorableMetadata implements BehaviorMetadataInterface
{
    /**
     * @param list<CreatedByBinding> $createdBindings
     * @param list<UpdatedByBinding> $updatedBindings
     * @param list<ChangedByBinding> $changedBindings
     * @param list<DeletedByBinding> $deletedBindings
     */
    public function __construct(
        public readonly array $createdBindings,
        public readonly array $updatedBindings,
        public readonly array $changedBindings,
        public readonly array $deletedBindings = [],
    ) {
    }

    public function getPersistBindings(): array
    {
        return $this->createdBindings;
    }

    public function getUpdateBindings(): array
    {
        return $this->updatedBindings;
    }

    public function getChangeBindings(): array
    {
        return $this->changedBindings;
    }

    /**
     * @return list<DeletedByBinding>
     */
    public function getDeletedBindings(): array
    {
        return $this->deletedBindings;
    }

    public function isEmpty(): bool
    {
        return $this->createdBindings === []
            && $this->updatedBindings === []
            && $this->changedBindings === []
            && $this->deletedBindings === [];
    }
}
