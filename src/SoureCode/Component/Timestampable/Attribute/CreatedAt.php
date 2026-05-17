<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Attribute;

use Doctrine\DBAL\Types\Types;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CreatedAt
{
    public function __construct(
        public readonly string $type = Types::DATETIMETZ_IMMUTABLE,
    ) {
    }
}
