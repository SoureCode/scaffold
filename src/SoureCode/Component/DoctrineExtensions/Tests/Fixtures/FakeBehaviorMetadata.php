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
     */
    public function __construct(
        private readonly array $persistBindings = [],
        private readonly array $updateBindings = [],
        private readonly array $changeBindings = [],
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

    public function isEmpty(): bool
    {
        return $this->persistBindings === []
            && $this->updateBindings === []
            && $this->changeBindings === [];
    }
}
