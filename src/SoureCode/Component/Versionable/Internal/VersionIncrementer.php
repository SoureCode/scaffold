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
        $unitOfWork->scheduleExtraUpdate($entity, [$versionField => [$current, $next]]);
        $unitOfWork->setOriginalEntityProperty(spl_object_id($entity), $versionField, $next);
    }
}
