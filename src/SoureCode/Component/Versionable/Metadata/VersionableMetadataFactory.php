<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;
use SoureCode\Component\Versionable\Attribute\Versioned;

class VersionableMetadataFactory extends AbstractBehaviorMetadataFactory
{
    /**
     * @var list<class-string>
     */
    private const array MAPPING_ATTRIBUTES = [
        Column::class,
        Embedded::class,
        ManyToOne::class,
        OneToOne::class,
        OneToMany::class,
        ManyToMany::class,
    ];

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): VersionableMetadata
    {
        if (isset($this->cache[$class])) {
            /** @var VersionableMetadata */
            return $this->cache[$class];
        }

        $bindings = [];

        if ($this->isVersionable($class)) {
            $this->walkHierarchy(
                $class,
                function (\ReflectionProperty $property) use (&$bindings): void {
                    if (!$this->isVersionedProperty($property)) {
                        return;
                    }

                    $bindings[] = new VersionedBinding($property);
                },
            );
        }

        $metadata = new VersionableMetadata($bindings);
        $this->cache[$class] = $metadata;

        return $metadata;
    }

    /**
     * @param class-string $class
     */
    public function isVersionable(string $class): bool
    {
        for (
            $reflection = new \ReflectionClass($class);
            $reflection !== false;
            $reflection = $reflection->getParentClass()
        ) {
            if ($reflection->getAttributes(Versioned::class) !== []) {
                return true;
            }
        }

        return false;
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

    private function isVersionedProperty(\ReflectionProperty $property): bool
    {
        if ($property->getAttributes(Id::class, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
            return false;
        }

        foreach (self::MAPPING_ATTRIBUTES as $mapping) {
            if ($property->getAttributes($mapping, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                return true;
            }
        }

        return false;
    }
}
