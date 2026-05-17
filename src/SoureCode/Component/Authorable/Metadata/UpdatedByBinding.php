<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface;

final class UpdatedByBinding implements UpdateBindingInterface
{
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly bool $nullable,
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
