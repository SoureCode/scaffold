<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

final class CreatedAtBinding
{
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly string $type,
    ) {
    }
}
