<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

final class UpdatedAtBinding
{
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly string $type,
        public readonly bool $nullable,
    ) {
    }
}
