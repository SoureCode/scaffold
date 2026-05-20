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

    /**
     * MUST return true iff every binding list returned by this metadata is empty
     * (`getPersistBindings`, `getUpdateBindings`, `getChangeBindings`, and any
     * additional lists implementations expose). Used by `AbstractFlushListener`
     * as a cheap short-circuit before entering the per-binding loop.
     */
    public function isEmpty(): bool;
}
