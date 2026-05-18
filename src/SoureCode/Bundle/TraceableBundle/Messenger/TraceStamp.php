<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

final class TraceStamp implements StampInterface
{
    public function __construct(
        public readonly Ulid $id,
    ) {
    }
}
