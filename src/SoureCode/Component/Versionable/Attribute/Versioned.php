<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Versioned
{
    public function __construct(
        public readonly bool $bumpRelations = true,
    ) {
    }
}
