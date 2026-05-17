<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface;

final class ChangedByBinding implements ChangeBindingInterface
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public readonly \ReflectionProperty $property,
        public readonly array $fields,
        public readonly bool $matchValue,
        public readonly mixed $value,
    ) {
    }

    public function getProperty(): \ReflectionProperty
    {
        return $this->property;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasValueMatcher(): bool
    {
        return $this->matchValue;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
