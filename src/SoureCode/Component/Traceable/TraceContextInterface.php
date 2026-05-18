<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

use Symfony\Component\Uid\Ulid;

interface TraceContextInterface
{
    public function getId(): Ulid;
}
