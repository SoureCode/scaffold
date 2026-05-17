<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

interface ChangeBindingInterface extends PersistBindingInterface
{
    /**
     * @return list<string>
     */
    public function getFields(): array;

    public function hasValueMatcher(): bool;

    public function getValue(): mixed;
}
