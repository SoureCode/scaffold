<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Event;

final class RecentAuthMarkedEvent
{
    public function __construct(
        public readonly int $atTimestamp,
    ) {
    }
}
