<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

final class FakePersistBinding implements PersistBindingInterface
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
