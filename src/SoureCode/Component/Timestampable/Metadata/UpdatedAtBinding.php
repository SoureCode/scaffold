<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface;

final class UpdatedAtBinding implements UpdateBindingInterface, TypedBindingInterface
{
    public function __construct(
        private readonly \ReflectionProperty $property,
        private readonly string $type,
        private readonly bool $nullable,
    ) {
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
