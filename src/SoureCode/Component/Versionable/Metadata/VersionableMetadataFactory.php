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

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getAttributes(Versioned::class) !== []) {
                    $bindings[] = new VersionedBinding($property);
                }
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->cache[$class] = new VersionableMetadata($bindings);
    }
}
