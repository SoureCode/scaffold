<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

final class TraceContext implements TraceContextInterface
{
    public function __construct(
        public readonly Ulid $id,
    ) {
    }

    public function getId(): Ulid
    {
        return $this->id;
    }
}
