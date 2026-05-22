<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Attribute;

use Doctrine\DBAL\Types\Types;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class DeletedAt
{
    public function __construct(
        public readonly string $type = Types::DATETIMETZ_IMMUTABLE,
    ) {
    }
}
