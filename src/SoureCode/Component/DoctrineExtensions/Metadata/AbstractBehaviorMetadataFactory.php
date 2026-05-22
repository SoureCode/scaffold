<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

/**
 * Shared scaffolding for behavior metadata factories that scan a class for
 * attribute-tagged properties (`#[CreatedAt]`, `#[Versioned]`, `#[ChangedBy]`,
 * …) and produce a typed metadata object.
 *
 * Each subclass:
 *
 *   - declares its own `getMetadataFor()` with a narrowed return type and
 *     defers to {@see walkHierarchy()} to collect bindings, then writes
 *     the result into the shared cache;
 *   - benefits from the leaf-first walk that filters by declaring class,
 *     so a property defined on the parent is visited exactly once (when
 *     we iterate the parent), never duplicated through inheritance, and
 *     a redeclared property on the child wins.
 *
 * `$cache` is `protected` so subclasses can reset it in tests, but its
 * lookup-or-build pattern lives entirely inside the subclass's
 * `getMetadataFor()` — the base does not provide a "magic" wrapper so the
 * concrete return type stays visible at the call site.
 */
abstract class AbstractBehaviorMetadataFactory
{
    /**
     * @var array<class-string, object>
     */
    protected array $cache = [];

    /**
     * @param class-string $class
     * @param callable(\ReflectionProperty): void $collect
     */
    protected function walkHierarchy(string $class, callable $collect): void
    {
        $seen = [];

        for (
            $reflection = new \ReflectionClass($class);
            $reflection !== false;
            $reflection = $reflection->getParentClass()
        ) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }

                $name = $property->getName();

                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $collect($property);
            }
        }
    }
}
