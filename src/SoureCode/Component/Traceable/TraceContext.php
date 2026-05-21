<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

final class TraceContext implements TraceContextInterface
{
    /**
     * @param array<string, bool|float|int|string|null> $attributes
     */
    public function __construct(
        public readonly Ulid $id,
        public readonly array $attributes = [],
    ) {
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $key): bool|float|int|string|null
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Returns a new context with the given attribute merged in. The original
     * instance is left untouched so a stale reference cannot be mutated by
     * a downstream consumer.
     */
    public function withAttribute(string $key, bool|float|int|string|null $value): self
    {
        return new self($this->id, array_replace($this->attributes, [$key => $value]));
    }
}
