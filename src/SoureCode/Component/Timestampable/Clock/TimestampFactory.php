<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Clock;

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
