<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

/**
 * Builds the per-class binding inventory Versionable tracks. The binding
 * list is drawn from Doctrine's `ClassMetadata` — every scalar/enum
 * field, embedded value object, and association declared on the entity
 * by any means (attributes, XML/YAML, `mapField()`/`mapManyToOne()`
 * called from a `loadClassMetadata` listener like
 * `AuthorableMappingListener`). The factory only needs reflection to
 * detect Versionable's own attributes (`#[Versioned]` / `#[Version]`).
 *
 * Excluded from bindings:
 *
 *   - the identifier(s),
 *   - Versionable's own `#[Version]` counter field,
 *   - Doctrine's optimistic-lock `#[ORM\Version]` field (concurrency
 *     metadata, not tracked data).
 */
class VersionableMetadataFactory extends AbstractBehaviorMetadataFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): VersionableMetadata
    {
        if (isset($this->cache[$class])) {
            /** @var VersionableMetadata */
            return $this->cache[$class];
        }

        $attribute = $this->getVersionedAttribute($class);

        if ($attribute === null) {
            $metadata = new VersionableMetadata([], null, null);
            $this->cache[$class] = $metadata;

            return $metadata;
        }

        $versionField = $this->findVersionField($class);
        $classMetadata = $this->entityManager->getClassMetadata($class);
        $bindings = $this->buildBindings($classMetadata, $versionField);

        $metadata = new VersionableMetadata($bindings, $versionField, $attribute->bumpRelations);
        $this->cache[$class] = $metadata;

        return $metadata;
    }

    /**
     * @param class-string $class
     */
    public function isVersionable(string $class): bool
    {
        return $this->getVersionedAttribute($class) !== null;
    }

    /**
     * Maps a source table name onto the version table name. Instance
     * method (not static) so hosts can subclass the factory and override
     * for multi-tenant schemas, custom suffixes, schema-qualified names,
     * etc. Override here and inject the subclass — every listener and
     * Versioner reads through this instance.
     */
    public function versionTableName(string $sourceTable): string
    {
        return $sourceTable . '_version';
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     *
     * @return list<VersionedBinding>
     */
    private function buildBindings(ClassMetadata $classMetadata, ?string $ownVersionField): array
    {
        $bindings = [];
        $seen = [];

        foreach ($classMetadata->fieldMappings as $field => $mapping) {
            // Embedded sub-fields land in fieldMappings as dotted entries
            // ("geo.latitude"); the embedded parent ("geo") is captured
            // through $embeddedClasses below.
            if (str_contains($field, '.')) {
                continue;
            }

            if ($classMetadata->isIdentifier($field)) {
                continue;
            }

            if ($field === $ownVersionField) {
                continue;
            }

            if ($classMetadata->isVersioned && $classMetadata->versionField === $field) {
                continue;
            }

            $bindings[] = new VersionedBinding($classMetadata->getReflectionProperty($field));
            $seen[$field] = true;
        }

        foreach (array_keys($classMetadata->embeddedClasses) as $field) {
            if (isset($seen[$field])) {
                continue;
            }

            $bindings[] = new VersionedBinding($classMetadata->getReflectionProperty($field));
            $seen[$field] = true;
        }

        foreach (array_keys($classMetadata->associationMappings) as $field) {
            if (isset($seen[$field])) {
                continue;
            }

            $bindings[] = new VersionedBinding($classMetadata->getReflectionProperty($field));
            $seen[$field] = true;
        }

        return $bindings;
    }

    /**
     * @param class-string $class
     */
    private function findVersionField(string $class): ?string
    {
        $found = null;

        $this->walkHierarchy(
            $class,
            static function (\ReflectionProperty $property) use (&$found): void {
                if ($found !== null) {
                    return;
                }

                if ($property->getAttributes(Version::class) !== []) {
                    $found = $property->getName();
                }
            },
        );

        return $found;
    }

    /**
     * @param class-string $class
     */
    private function getVersionedAttribute(string $class): ?Versioned
    {
        for (
            $reflection = new \ReflectionClass($class);
            $reflection !== false;
            $reflection = $reflection->getParentClass()
        ) {
            $attributes = $reflection->getAttributes(Versioned::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }
}
