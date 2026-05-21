<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;
use SoureCode\Component\Authorable\AuthorableInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadata;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractFlushListener;

final class AuthorableListener extends AbstractFlushListener
{
    public function __construct(
        private readonly AuthorProviderInterface $authorProvider,
        AuthorableMetadataFactory $metadataFactory,
        ChangeSetMatcher $changeSetMatcher,
        private readonly ?ImpersonatorProviderInterface $impersonatorProvider = null,
    ) {
        parent::__construct($metadataFactory, $changeSetMatcher);
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        parent::prePersist($args);

        if ($this->impersonatorProvider === null) {
            return;
        }

        $impersonator = $this->impersonatorProvider->getImpersonator();

        if ($impersonator === null) {
            return;
        }

        $entity = $args->getObject();
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        if (!$metadata instanceof AuthorableMetadata) {
            return;
        }

        foreach ($metadata->getImpersonatedBindings() as $binding) {
            if ($binding->getProperty()->getValue($entity) === null) {
                $binding->getProperty()->setValue($entity, $impersonator);
            }
        }
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

        if (array_key_exists('updatedBy', $unitOfWork->getEntityChangeSet($entity))) {
            return;
        }

        $entity->setUpdatedBy($this->authorProvider->getCurrentAuthor());
        $unitOfWork->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity,
        );
    }
}
