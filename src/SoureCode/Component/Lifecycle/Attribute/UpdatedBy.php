<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UpdatedBy
{
    public function __construct(
        public readonly bool $nullable = true,
    ) {
    }
}
