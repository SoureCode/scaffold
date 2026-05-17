<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface;

final class FakeChangeBinding implements ChangeBindingInterface
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        private readonly \ReflectionProperty $property,
        private readonly array $fields,
        private readonly bool $matchValue = false,
        private readonly mixed $value = null,
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
