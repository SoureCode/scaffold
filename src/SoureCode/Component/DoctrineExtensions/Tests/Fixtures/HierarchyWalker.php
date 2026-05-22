<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;

/**
 * Test-only subclass of {@see AbstractBehaviorMetadataFactory} that
 * forwards calls to the protected `walkHierarchy` helper so unit tests
 * can exercise it directly. Real factories (Authorable / Timestampable /
 * Versionable) drive `walkHierarchy` from inside `getMetadataFor`.
 */
final class HierarchyWalker extends AbstractBehaviorMetadataFactory
{
    /**
     * @param class-string $class
     *
     * @return list<array{class: class-string, property: string}>
     */
    public function visitedProperties(string $class): array
    {
        $visited = [];

        $this->walkHierarchy(
            $class,
            static function (\ReflectionProperty $property) use (&$visited): void {
                $visited[] = [
                    'class' => $property->getDeclaringClass()->getName(),
                    'property' => $property->getName(),
                ];
            },
        );

        return $visited;
    }
}
