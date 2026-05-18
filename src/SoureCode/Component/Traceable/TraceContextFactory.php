<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

final class TraceContextFactory
{
    public function create(?Ulid $id = null): TraceContext
    {
        return new TraceContext($id ?? new Ulid());
    }
}
