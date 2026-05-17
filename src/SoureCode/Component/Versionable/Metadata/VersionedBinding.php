<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

final class VersionedBinding
{
    public function __construct(
        public readonly \ReflectionProperty $property,
    ) {
    }
}
