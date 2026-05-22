<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;
use SoureCode\Component\Versionable\Attribute\Versioned;

class VersionableMetadataFactory extends AbstractBehaviorMetadataFactory
{

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

        $this->walkHierarchy(
            $class,
            static function (\ReflectionProperty $property) use (&$bindings): void {
                if ($property->getAttributes(Versioned::class) === []) {
                    return;
                }

                $bindings[] = new VersionedBinding($property);
            },
        );

        $metadata = new VersionableMetadata($bindings);
        $this->cache[$class] = $metadata;

        return $metadata;
    }

    /**
     * @param class-string $class
     */
    public function isVersionable(string $class): bool
    {
        return !$this->getMetadataFor($class)->isEmpty();
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
}
