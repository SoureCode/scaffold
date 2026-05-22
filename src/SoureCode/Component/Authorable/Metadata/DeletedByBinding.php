<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

final class DeletedByBinding
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
