<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

final class ImpersonatedByBinding implements PersistBindingInterface
{
    public function __construct(
        private readonly \ReflectionProperty $property,
    ) {
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }
}
