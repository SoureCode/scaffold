<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\Internal\SnapshotTargetResolver;
use SoureCode\Component\Versionable\Internal\SnapshotWriter;
use SoureCode\Component\Versionable\Internal\VersionIncrementer;

final class VersionableListener
{
    /**
     * @var \SplObjectStorage<object, true>|null
     */
    private ?\SplObjectStorage $pendingSnapshots = null;

    public function __construct(
        private readonly SnapshotTargetResolver $targetResolver,
        private readonly VersionIncrementer $versionIncrementer,
        private readonly SnapshotWriter $snapshotWriter,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();

        $targets = $this->pendingSnapshots ?? new \SplObjectStorage();

        foreach ($this->targetResolver->resolve($entityManager) as $entity) {
            $targets[$entity] = true;
        }

        foreach ($targets as $entity) {
            $this->versionIncrementer->increment($entity, $entityManager);
        }

        $this->pendingSnapshots = $targets;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingSnapshots === null || $this->pendingSnapshots->count() === 0) {
            $this->pendingSnapshots = null;

            return;
        }

        $entityManager = $args->getObjectManager();
        $pending = $this->pendingSnapshots;
        $this->pendingSnapshots = null;

        // postFlush fires after the entity transaction has committed; wrap the
        // snapshot writes in their own transaction so a mid-batch failure rolls
        // back the partial audit history instead of leaving half written.
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($pending as $entity) {
                $this->snapshotWriter->write($entity, $entityManager);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            $committed = [];

            foreach ($pending as $entity) {
                $committed[] = $entity::class;
            }

            $this->logger->error(
                'Versionable: entity changes were committed but the audit snapshot transaction failed; history for {classes} is missing.',
                [
                    'classes' => implode(', ', array_unique($committed)),
                    'exception' => $exception,
                ],
            );

            throw $exception;
        }
    }
}
