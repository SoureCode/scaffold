<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

interface TraceContextInterface
{
    public function getId(): Ulid;

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function getAttributes(): array;

    public function getAttribute(string $key): bool|float|int|string|null;
}
