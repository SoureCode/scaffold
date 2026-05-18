<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

final class Remover implements RemoverInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly TimestampableMetadataFactory $timestampableMetadata,
        private readonly AuthorableMetadataFactory $authorableMetadata,
        private readonly ?AuthorProviderInterface $authorProvider = null,
    ) {
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function remove(object $entity, bool $soft = true, bool $flush = true): void
    {
        if ($soft) {
            $this->fillDeletionMarkers($entity);
        } else {
            $this->entityManager->remove($entity);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    public function restore(object $entity, bool $flush = true): void
    {
        $this->clearDeletionMarkers($entity);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function fillDeletionMarkers(object $entity): void
    {
        $deletedAtBindings = $this->timestampableMetadata
            ->getMetadataFor($entity::class)
            ->getDeletedBindings();

        if ($deletedAtBindings === []) {
            throw new \LogicException(\sprintf(
                'Entity "%s" has no #[DeletedAt] marker — cannot soft-remove.',
                $entity::class,
            ));
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($deletedAtBindings as $binding) {
            $binding->getProperty()->setValue($entity, $now);
        }

        $author = $this->authorProvider?->getCurrentAuthor();

        if ($author === null) {
            return;
        }

        foreach ($this->authorableMetadata->getMetadataFor($entity::class)->getDeletedBindings() as $binding) {
            $binding->getProperty()->setValue($entity, $author);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    private function clearDeletionMarkers(object $entity): void
    {
        $deletedAtBindings = $this->timestampableMetadata
            ->getMetadataFor($entity::class)
            ->getDeletedBindings();

        if ($deletedAtBindings === []) {
            throw new \LogicException(\sprintf(
                'Entity "%s" has no #[DeletedAt] marker — cannot restore.',
                $entity::class,
            ));
        }

        foreach ($deletedAtBindings as $binding) {
            $binding->getProperty()->setValue($entity, null);
        }

        foreach ($this->authorableMetadata->getMetadataFor($entity::class)->getDeletedBindings() as $binding) {
            $binding->getProperty()->setValue($entity, null);
        }
    }
}
