<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\EventListener;

use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractMetadataMappingListener;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadata;
use SoureCode\Component\Lifecycle\Metadata\TypedBindingInterface;

final class TimestampableMappingListener extends AbstractMetadataMappingListener
{
    protected function mapIfMissing(
        ClassMetadata $classMetadata,
        PersistBindingInterface $binding,
        bool $nullable,
    ): void {
        \assert($binding instanceof TypedBindingInterface);

        $fieldName = $binding->getProperty()->getName();

        if ($classMetadata->hasField($fieldName)) {
            return;
        }

        $classMetadata->mapField([
            'fieldName' => $fieldName,
            'type' => $binding->getType(),
            'nullable' => $nullable,
        ]);
    }

    protected function getDeletedBindings(BehaviorMetadataInterface $metadata): iterable
    {
        \assert($metadata instanceof TimestampableMetadata);

        return $metadata->getDeletedBindings();
    }
}
