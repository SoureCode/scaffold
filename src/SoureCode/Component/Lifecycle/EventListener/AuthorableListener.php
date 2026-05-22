<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Component\Lifecycle\AuthorableInterface;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractFlushListener;

final class AuthorableListener extends AbstractFlushListener
{
    public function __construct(
        private readonly AuthorProviderInterface $authorProvider,
        AuthorableMetadataFactory $metadataFactory,
        ChangeSetMatcher $changeSetMatcher,
    ) {
        parent::__construct($metadataFactory, $changeSetMatcher);
    }

    protected function shouldRun(): bool
    {
        return $this->authorProvider->getCurrentAuthor() !== null;
    }

    protected function resolveValue(\ReflectionProperty $property): mixed
    {
        return $this->authorProvider->getCurrentAuthor();
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    protected function handlePersistInterfaceFallback(object $entity): void
    {
        if (!$entity instanceof AuthorableInterface) {
            return;
        }

        $author = $this->authorProvider->getCurrentAuthor();

        if ($entity->getCreatedBy() === null) {
            $entity->setCreatedBy($author);
        }

        if ($entity->getUpdatedBy() === null) {
            $entity->setUpdatedBy($author);
        }
    }

    /**
     * @template T of object
     *
     * @param T $entity
     */
    protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        if (!$entity instanceof AuthorableInterface) {
            return;
        }

        $this->applyInterfaceUpdate(
            $entity,
            $entityManager,
            $unitOfWork,
            AuthorableInterface::UPDATED_BY_PROPERTY,
            function (AuthorableInterface $authorable): void {
                $authorable->setUpdatedBy($this->authorProvider->getCurrentAuthor());
            },
        );
    }
}
