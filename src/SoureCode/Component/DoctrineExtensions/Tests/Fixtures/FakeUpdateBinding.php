<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface;

final class FakeUpdateBinding implements UpdateBindingInterface
{
    public function __construct(
        private readonly \ReflectionProperty $property,
        private readonly bool $nullable = true,
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
