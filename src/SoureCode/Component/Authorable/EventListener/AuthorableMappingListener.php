<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class AuthorableMappingListener
{
    public function __construct(
        private readonly AuthorableMetadataFactory $metadataFactory,
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
            $this->mapIfMissing($classMetadata, $binding->property, false);
        }

        foreach ($metadata->updatedBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property, $binding->nullable);
        }

        foreach ($metadata->changedBindings as $binding) {
            $this->mapIfMissing($classMetadata, $binding->property, true);
        }
    }

    private function mapIfMissing(ClassMetadata $classMetadata, \ReflectionProperty $property, bool $nullable): void
    {
        $fieldName = $property->getName();

        if ($classMetadata->hasAssociation($fieldName)) {
            return;
        }

        if ($classMetadata->hasField($fieldName)) {
            return;
        }

        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new \LogicException(\sprintf(
                'Authorable mapping requires an object type on "%s::$%s", got %s.',
                $classMetadata->getName(),
                $fieldName,
                $type === null ? 'no type' : (string) $type,
            ));
        }

        $classMetadata->mapManyToOne([
            'fieldName' => $fieldName,
            'targetEntity' => $type->getName(),
            'joinColumns' => [[
                'name' => null,
                'referencedColumnName' => 'id',
                'nullable' => $nullable,
            ]],
        ]);
    }
}
