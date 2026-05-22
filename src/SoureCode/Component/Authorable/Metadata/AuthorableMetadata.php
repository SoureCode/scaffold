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
     * @param list<ImpersonatedByBinding> $impersonatedBindings
     */
    public function __construct(
        private readonly array $createdBindings,
        private readonly array $updatedBindings,
        private readonly array $changedBindings,
        private readonly array $deletedBindings = [],
        private readonly array $impersonatedBindings = [],
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
     * Outside `BehaviorMetadataInterface` — only `AuthorableMappingListener` consumes these.
     * The base flush listener never touches `#[DeletedBy]`; clearing is done by `Remover`.
     *
     * @return list<DeletedByBinding>
     */
    public function getDeletedBindings(): array
    {
        return $this->deletedBindings;
    }

    /**
     * @return list<ImpersonatedByBinding>
     */
    public function getImpersonatedBindings(): array
    {
        return $this->impersonatedBindings;
    }

    public function isEmpty(): bool
    {
        return $this->createdBindings === []
            && $this->updatedBindings === []
            && $this->changedBindings === []
            && $this->deletedBindings === []
            && $this->impersonatedBindings === [];
    }
}
