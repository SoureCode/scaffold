<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

interface BehaviorMetadataInterface
{
    /**
     * @return list<PersistBindingInterface>
     */
    public function getPersistBindings(): array;

    /**
     * @return list<UpdateBindingInterface>
     */
    public function getUpdateBindings(): array;

    /**
     * @return list<ChangeBindingInterface>
     */
    public function getChangeBindings(): array;

    public function isEmpty(): bool;
}
