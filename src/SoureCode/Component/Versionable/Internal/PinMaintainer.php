<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\EventListener\VersionTableColumns;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Writes the `<field>_version` pin columns on the LIVE owning table
 *           for a versioned entity's single-valued associations. Runs in
 *           postFlush — the entity already has an id and the related entities'
 *           versions are settled in memory.
 *
 * A pin captures the related entity's current version at the moment of the
 * owner's flush. From there it is frozen until the owner is flushed again,
 * regardless of whether the related entity bumps independently. That is what
 * makes `$post->getAuthorHistory()` "see" the author as it was, not as it is.
 */
final class PinMaintainer
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    public function maintain(object $entity, EntityManagerInterface $entityManager): void
    {
        $classMetadata = $entityManager->getClassMetadata($entity::class);
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        $pins = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if (!$classMetadata->isSingleValuedAssociation($fieldName)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($fieldName);

            if (!$assoc->isOwningSide()) {
                continue;
            }

            if (!$this->metadataFactory->isVersionable($assoc->targetEntity)) {
                continue;
            }

            $related = $binding->property->getValue($entity);
            $pinColumn = $fieldName . VersionTableColumns::SINGLE_ASSOC_VERSION_SUFFIX;

            if ($related === null) {
                $pins[$pinColumn] = null;

                continue;
            }

            $targetVersionField = $this->metadataFactory->getMetadataFor($assoc->targetEntity)->versionField;

            if ($targetVersionField === null) {
                $pins[$pinColumn] = null;

                continue;
            }

            $relatedVersion = (int) $entityManager->getClassMetadata($assoc->targetEntity)
                ->getFieldValue($related, $targetVersionField);

            if ($relatedVersion === 0) {
                throw new \RuntimeException(\sprintf(
                    'Cannot pin %s::$%s to %s — the target has no snapshot yet (version is 0). Persist the target before assigning it as a relation.',
                    $entity::class,
                    $fieldName,
                    $assoc->targetEntity,
                ));
            }

            $pins[$pinColumn] = $relatedVersion;
        }

        if ($pins === []) {
            return;
        }

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $entityId = $classMetadata->getReflectionProperty($idField)->getValue($entity);

        if ($entityId === null) {
            return;
        }

        $entityManager->getConnection()->update(
            $classMetadata->getTableName(),
            $pins,
            [$classMetadata->getColumnName($idField) => $entityId],
        );
    }
}
