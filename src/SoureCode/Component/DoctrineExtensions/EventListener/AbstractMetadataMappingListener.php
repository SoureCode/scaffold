<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

/**
 * Lifts the loadClassMetadata boilerplate that every behavior bundle's
 * mapping listener used to copy: pull metadata for the class, skip when
 * empty, then walk persist / update / change / deleted bindings applying
 * the same nullability convention.
 *
 * Nullability convention (consistent across Authorable & Timestampable):
 *   - persist  → nullable=false (stamped on insert, never reverts to null)
 *   - update   → nullable from the binding itself (attribute author decides)
 *   - change   → nullable=true  (populated lazily by a field-watch)
 *   - deleted  → nullable=true  (populated lazily by a soft-delete)
 *
 * Subclasses provide the per-listener piece — the actual Doctrine mapping
 * call (field vs ManyToOne) — and tell the base where to find the
 * deleted-bindings collection on their concrete metadata type.
 */
abstract class AbstractMetadataMappingListener
{
    public function __construct(
        protected readonly BehaviorMetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();
        $metadata = $this->metadataFactory->getMetadataFor($classMetadata->getName());

        if ($metadata->isEmpty()) {
            return;
        }

        foreach ($metadata->getPersistBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding, false);
        }

        foreach ($metadata->getUpdateBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding, $binding->isNullable());
        }

        foreach ($metadata->getChangeBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding, true);
        }

        foreach ($this->getDeletedBindings($metadata) as $binding) {
            $this->mapIfMissing($classMetadata, $binding, true);
        }
    }

    /**
     * Apply the listener's specific Doctrine mapping for one binding when
     * the field/association does not yet exist on the entity. Implementations
     * MUST be a no-op when the field/association is already mapped — this is
     * the behavior that lets a user pre-declare the column manually and
     * still inherit the rest of the behavior.
     */
    abstract protected function mapIfMissing(
        ClassMetadata $classMetadata,
        PersistBindingInterface $binding,
        bool $nullable,
    ): void;

    /**
     * Concrete behavior metadata classes hold their deletion bindings under
     * a `getDeletedBindings()` getter that is not part of
     * {@see BehaviorMetadataInterface} (Versionable, for example, has no
     * deletion concept). Subclasses cast the supplied metadata to their
     * known concrete type and return the collection.
     *
     * @return iterable<PersistBindingInterface>
     */
    abstract protected function getDeletedBindings(BehaviorMetadataInterface $metadata): iterable;
}
