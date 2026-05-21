<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Messenger;

final class PruneVersionableHistoryMessage
{
    /**
     * @param class-string $className
     */
    public function __construct(
        public readonly string $className,
        public readonly \DateTimeImmutable $olderThan,
        public readonly int $keepLast = 1,
    ) {
    }
}
