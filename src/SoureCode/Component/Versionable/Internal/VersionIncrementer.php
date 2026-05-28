<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Advances a versioned entity's own `#[Version]` counter for the
 *           current flush. The counter is a plain mapped field, so Doctrine's
 *           persister writes it natively via an extra update; assigning the new
 *           original value keeps the entity clean so a subsequent no-op flush
 *           does not bump it again.
 */
final class VersionIncrementer
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    public function increment(object $entity, EntityManagerInterface $entityManager): void
    {
        $classMetadata = $entityManager->getClassMetadata($entity::class);
        $versionField = $this->metadataFactory->getMetadataFor($entity::class)->versionField;

        if ($versionField === null) {
            throw new \RuntimeException(\sprintf('Versioned entity %s must declare a #[Version] property.', $classMetadata->getName()));
        }

        $current = (int) $classMetadata->getFieldValue($entity, $versionField);
        $next = $current + 1;
        $unitOfWork = $entityManager->getUnitOfWork();

        $classMetadata->setFieldValue($entity, $versionField, $next);

        if ($unitOfWork->isScheduledForInsert($entity)) {
            // The insert changeset was captured in computeScheduleInsertsChangeSets
            // BEFORE onFlush fired, so the INSERT SQL still carries the
            // pre-bump value (0). Recompute so the INSERT writes the bumped
            // version on the live row — otherwise the live row stays at 0,
            // the next flush re-reads 0, bumps to 1, and collides with the
            // v=1 snapshot we already wrote for the insert.
            $unitOfWork->recomputeSingleEntityChangeSet($classMetadata, $entity);

            return;
        }

        $unitOfWork->scheduleExtraUpdate($entity, [$versionField => [$current, $next]]);
        $unitOfWork->setOriginalEntityProperty(spl_object_id($entity), $versionField, $next);
    }
}
