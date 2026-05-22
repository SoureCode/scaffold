<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\EventListener;

use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadata;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractMetadataMappingListener;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

final class AuthorableMappingListener extends AbstractMetadataMappingListener
{
    /**
     * @param class-string|null $userClass when set, overrides the property's PHP type as the target entity for every binding
     */
    public function __construct(
        AuthorableMetadataFactory $metadataFactory,
        private readonly ?string $userClass = null,
    ) {
        parent::__construct($metadataFactory);
    }

    protected function mapIfMissing(
        ClassMetadata $classMetadata,
        PersistBindingInterface $binding,
        bool $nullable,
    ): void {
        $property = $binding->getProperty();
        $fieldName = $property->getName();

        if ($classMetadata->hasAssociation($fieldName)) {
            return;
        }

        if ($classMetadata->hasField($fieldName)) {
            return;
        }

        $targetEntity = $this->userClass ?? $this->resolveTargetFromProperty($classMetadata->getName(), $property);

        $classMetadata->mapManyToOne([
            'fieldName' => $fieldName,
            'targetEntity' => $targetEntity,
            'joinColumns' => [[
                // 'name' => null lets Doctrine derive the column name from the field (e.g. `created_by_id`).
                'name' => null,
                'referencedColumnName' => 'id',
                'nullable' => $nullable,
            ]],
        ]);
    }

    protected function getDeletedBindings(BehaviorMetadataInterface $metadata): iterable
    {
        \assert($metadata instanceof AuthorableMetadata);

        return $metadata->getDeletedBindings();
    }

    /**
     * @param class-string $className
     */
    private function resolveTargetFromProperty(string $className, \ReflectionProperty $property): string
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new \LogicException(\sprintf(
                'Authorable mapping requires an object type on "%s::$%s", got %s. Configure "user_class" to override.',
                $className,
                $property->getName(),
                $type === null ? 'no type' : (string) $type,
            ));
        }

        $name = $type->getName();

        if (!class_exists($name)) {
            throw new \LogicException(\sprintf(
                'Authorable mapping cannot use non-class type "%s" on "%s::$%s". Configure "user_class" to point at a concrete entity.',
                $name,
                $className,
                $property->getName(),
            ));
        }

        return $name;
    }
}
