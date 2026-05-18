<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable\Repository;

use Psr\Clock\ClockInterface;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

trait RemovableRepositoryTrait
{
    private ClockInterface $clock;
    private TimestampableMetadataFactory $timestampableMetadata;
    private AuthorableMetadataFactory $authorableMetadata;
    private ?AuthorProviderInterface $authorProvider = null;

    public function remove(object $entity, bool $soft = true, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();

        if ($soft) {
            $this->fillDeletionMarkers($entity);
        } else {
            $entityManager->remove($entity);
        }

        if ($flush) {
            $entityManager->flush();
        }
    }

    public function restore(object $entity, bool $flush = true): void
    {
        $this->clearDeletionMarkers($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

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
