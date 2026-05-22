<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface;

final class FakeBehaviorMetadata implements BehaviorMetadataInterface
{
    /**
     * @param list<PersistBindingInterface> $persistBindings
     * @param list<UpdateBindingInterface> $updateBindings
     * @param list<ChangeBindingInterface> $changeBindings
     * @param list<PersistBindingInterface> $deletedBindings  Not part of the
     *        public behavior interface (Versionable has no deletion concept);
     *        consumed by the mapping-listener tests via a known concrete
     *        type, mirroring how AuthorableMetadata / TimestampableMetadata
     *        expose theirs.
     */
    public function __construct(
        private readonly array $persistBindings = [],
        private readonly array $updateBindings = [],
        private readonly array $changeBindings = [],
        private readonly array $deletedBindings = [],
    ) {
    }

    public function getPersistBindings(): array
    {
        return $this->persistBindings;
    }

    public function getUpdateBindings(): array
    {
        return $this->updateBindings;
    }

    public function getChangeBindings(): array
    {
        return $this->changeBindings;
    }

    /**
     * @return list<PersistBindingInterface>
     */
    public function getDeletedBindings(): array
    {
        return $this->deletedBindings;
    }

    public function isEmpty(): bool
    {
        return $this->persistBindings === []
            && $this->updateBindings === []
            && $this->changeBindings === []
            && $this->deletedBindings === [];
    }
}
