<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

use SoureCode\Component\Versionable\Attribute\Versioned;

class VersionableMetadataFactory
{
    /**
     * @var array<class-string, VersionableMetadata>
     */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): VersionableMetadata
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $bindings = [];
        $seen = [];

        // ReflectionClass::getProperties() does not return inherited
        // properties whose declaring class is a parent; walk the hierarchy
        // root-first so parent properties come before child overrides.
        $hierarchy = [];
        for ($reflection = new \ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $hierarchy[] = $reflection;
        }

        foreach (array_reverse($hierarchy) as $reflection) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }

                if (isset($seen[$property->getName()])) {
                    continue;
                }

                if ($property->getAttributes(Versioned::class) === []) {
                    continue;
                }

                $seen[$property->getName()] = true;
                $bindings[] = new VersionedBinding($property);
            }
        }

        return $this->cache[$class] = new VersionableMetadata($bindings);
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
