<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

final class ChangedAtBinding
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly array $fields,
        public readonly bool $matchValue,
        public readonly mixed $value,
        public readonly string $type,
    ) {
    }

    public function hasValueMatcher(): bool
    {
        return $this->matchValue;
    }
}
