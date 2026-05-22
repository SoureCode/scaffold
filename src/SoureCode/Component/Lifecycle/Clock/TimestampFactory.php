<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Clock;

use Psr\Clock\ClockInterface;

final class TimestampFactory
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function makeFor(\ReflectionProperty $property): \DateTimeInterface|int
    {
        $now = $this->clock->now();
        $type = $property->getType();

        if ($type instanceof \ReflectionNamedType) {
            // Match is exact-class only: `\DateTime` selects the mutable branch,
            // anything else (`\DateTimeImmutable`, `\DateTimeInterface`, …) falls
            // through to the immutable default. Don't reorder without re-checking.
            return match ($type->getName()) {
                'int' => (int) $now->format('U'),
                \DateTime::class => \DateTime::createFromInterface($now),
                default => \DateTimeImmutable::createFromInterface($now),
            };
        }

        return \DateTimeImmutable::createFromInterface($now);
    }

    public function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
