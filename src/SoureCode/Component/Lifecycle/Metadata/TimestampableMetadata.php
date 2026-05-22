<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;

final class TimestampableMetadata implements BehaviorMetadataInterface
{
    /**
     * @param list<CreatedAtBinding> $createdBindings
     * @param list<UpdatedAtBinding> $updatedBindings
     * @param list<ChangedAtBinding> $changedBindings
     * @param list<DeletedAtBinding> $deletedBindings
     */
    public function __construct(
        private readonly array $createdBindings,
        private readonly array $updatedBindings,
        private readonly array $changedBindings,
        private readonly array $deletedBindings = [],
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
     * @return list<DeletedAtBinding>
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
