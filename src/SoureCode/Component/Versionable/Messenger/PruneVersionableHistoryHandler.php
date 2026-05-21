<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Messenger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\VersionerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PruneVersionableHistoryHandler
{
    public function __construct(
        private readonly VersionerInterface $versioner,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(PruneVersionableHistoryMessage $message): void
    {
        $deleted = $this->versioner->prune($message->className, $message->olderThan, $message->keepLast);

        $this->logger->info(
            'Versionable: pruned {deleted} version rows of {class} older than {cutoff} (keepLast={keepLast}).',
            [
                'deleted' => $deleted,
                'class' => $message->className,
                'cutoff' => $message->olderThan->format(\DateTimeInterface::ATOM),
                'keepLast' => $message->keepLast,
            ],
        );
    }
}
