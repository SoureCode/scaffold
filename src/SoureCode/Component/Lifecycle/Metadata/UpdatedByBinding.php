<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface;

final class UpdatedByBinding implements UpdateBindingInterface
{
    public function __construct(
        private readonly \ReflectionProperty $property,
        private readonly bool $nullable,
    ) {
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
