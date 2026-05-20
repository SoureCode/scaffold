<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractFlushListener;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;

final class FakeFlushListener extends AbstractFlushListener
{
    public int $resolveValueCalls = 0;

    public int $persistFallbackCalls = 0;

    public int $updateFallbackCalls = 0;

    /**
     * @var int|null Counter snapshot when shouldRun returned true; null otherwise
     */
    public ?int $lastShouldRunSnapshot = null;

    /**
     * @param callable(): string $stampFactory
     */
    public function __construct(
        BehaviorMetadataFactoryInterface $metadataFactory,
        ChangeSetMatcher $changeSetMatcher,
        private readonly mixed $stampFactory = null,
        private readonly bool $enabled = true,
    ) {
        parent::__construct($metadataFactory, $changeSetMatcher);
    }

    protected function shouldRun(): bool
    {
        return $this->enabled;
    }

    protected function resolveValue(\ReflectionProperty $property): mixed
    {
        ++$this->resolveValueCalls;

        return $this->stampFactory !== null ? ($this->stampFactory)() : 'stamp';
    }

    protected function handlePersistInterfaceFallback(object $entity): void
    {
        ++$this->persistFallbackCalls;

        if ($entity instanceof StampableInterface && $entity->getInterfaceStamp() === null) {
            $entity->setInterfaceStamp('persist-fallback');
        }
    }

    protected function handleUpdateInterfaceFallback(object $entity, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        ++$this->updateFallbackCalls;

        if ($entity instanceof StampableInterface) {
            $entity->setInterfaceStamp('update-fallback');
            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata($entity::class),
                $entity,
            );
        }
    }
}
