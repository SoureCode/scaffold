<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

final class CreatedAtBinding implements PersistBindingInterface
{
    public function __construct(
        private readonly \ReflectionProperty $property,
        private readonly string $type,
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
}
