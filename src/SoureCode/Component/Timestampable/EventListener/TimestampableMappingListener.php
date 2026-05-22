<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

final class TimestampableMappingListener
{
    public function __construct(
        private readonly TimestampableMetadataFactory $metadataFactory,
    ) {
    }

    /**
     * Nullability rules per binding kind:
     *   - #[CreatedAt]: always nullable=false; stamped on insert.
     *   - #[UpdatedAt]: nullability comes from the binding so the attribute author can
     *                   model "not yet stamped" without resorting to a sentinel value.
     *   - #[ChangedAt] / #[DeletedAt]: always nullable=true; populated lazily by a field
     *                   watch or soft-delete.
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();
        $metadata = $this->metadataFactory->getMetadataFor($classMetadata->getName());

        if ($metadata->isEmpty()) {
            return;
        }

        foreach ($metadata->getPersistBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding->getProperty()->getName(), $binding->getType(), false);
        }

        foreach ($metadata->getUpdateBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding->getProperty()->getName(), $binding->getType(), $binding->isNullable());
        }

        foreach ($metadata->getChangeBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding->getProperty()->getName(), $binding->getType(), true);
        }

        foreach ($metadata->getDeletedBindings() as $binding) {
            $this->mapIfMissing($classMetadata, $binding->getProperty()->getName(), $binding->getType(), true);
        }
    }

    private function mapIfMissing(ClassMetadata $classMetadata, string $fieldName, string $type, bool $nullable): void
    {
        if ($classMetadata->hasField($fieldName)) {
            return;
        }

        $classMetadata->mapField([
            'fieldName' => $fieldName,
            'type' => $type,
            'nullable' => $nullable,
        ]);
    }
}
