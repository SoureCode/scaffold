<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

final class TraceContextFactory
{
    /**
     * @param array<string, bool|float|int|string|null> $attributes
     */
    public function create(?Ulid $id = null, array $attributes = []): TraceContext
    {
        return new TraceContext($id ?? new Ulid(), $attributes);
    }
}
