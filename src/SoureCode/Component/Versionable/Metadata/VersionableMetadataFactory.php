<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

use SoureCode\Component\Versionable\Attribute\Versioned;

final class VersionableMetadataFactory
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
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Versioned::class) !== []) {
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

    public static function versionTableName(string $sourceTable): string
    {
        return $sourceTable . '_version';
    }
}
