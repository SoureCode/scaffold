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

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();
        $metadata = $this->metadataFactory->getMetadataFor($classMetadata->getName());

        if ($metadata->isEmpty()) {
            return;
        }

        foreach ($metadata->createdBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property->getName(), $binding->type, false);
        }

        foreach ($metadata->updatedBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property->getName(), $binding->type, $binding->nullable);
        }

        foreach ($metadata->changedBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property->getName(), $binding->type, true);
        }

        foreach ($metadata->deletedBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property->getName(), $binding->type, true);
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
