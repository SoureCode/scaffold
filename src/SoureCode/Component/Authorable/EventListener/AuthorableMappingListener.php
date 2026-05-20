<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class AuthorableMappingListener
{
    /**
     * @param class-string|null $userClass when set, overrides the property's PHP type as the target entity for every binding
     */
    public function __construct(
        private readonly AuthorableMetadataFactory $metadataFactory,
        private readonly ?string $userClass = null,
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

        foreach ($metadata->deletedBindings as $binding) {
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
