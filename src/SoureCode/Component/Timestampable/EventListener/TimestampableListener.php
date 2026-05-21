<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractFlushListener;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\TimestampableInterface;

final class TimestampableListener extends AbstractFlushListener
{
    public function __construct(
        TimestampableMetadataFactory $metadataFactory,
        private readonly TimestampFactory $timestampFactory,
        ChangeSetMatcher $changeSetMatcher,
    ) {
        parent::__construct($metadataFactory, $changeSetMatcher);
    }

    /**
     * Always true: timestamp stamping has no per-request precondition.
     * The hook exists on the base class for sibling behaviors (e.g. Authorable)
     * that disable themselves when no current author is available.
     */
    protected function shouldRun(): bool
    {
        return true;
    }

    protected function resolveValue(\ReflectionProperty $property): mixed
    {
        return $this->timestampFactory->makeFor($property);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    protected function handlePersistInterfaceFallback(object $entity): void
    {
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $now = $this->timestampFactory->now();

        if ($entity->getCreatedAt() === null) {
            $entity->setCreatedAt($now);
        }

        if ($entity->getUpdatedAt() === null) {
            $entity->setUpdatedAt($now);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        if (array_key_exists('updatedAt', $unitOfWork->getEntityChangeSet($entity))) {
            return;
        }

        $entity->setUpdatedAt($this->timestampFactory->now());
        $unitOfWork->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity,
        );
    }
}
