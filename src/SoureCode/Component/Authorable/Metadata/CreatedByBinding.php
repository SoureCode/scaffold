<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

final class CreatedByBinding implements PersistBindingInterface
{
    public function __construct(
        public readonly \ReflectionProperty $property,
    ) {
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }
}
