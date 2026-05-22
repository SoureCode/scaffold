<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\HierarchyChild;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\HierarchyParent;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\HierarchyWalker;

final class AbstractBehaviorMetadataFactoryTest extends TestCase
{
    public function testWalkVisitsAllPropertiesOnASingleClass(): void
    {
        $walker = new HierarchyWalker();

        $visited = $walker->visitedProperties(HierarchyParent::class);

        self::assertSame(
            [
                ['class' => HierarchyParent::class, 'property' => 'shared'],
                ['class' => HierarchyParent::class, 'property' => 'parentOnly'],
            ],
            $visited,
        );
    }

    public function testWalkIsLeafFirstAndFiltersByDeclaringClass(): void
    {
        $walker = new HierarchyWalker();

        $visited = $walker->visitedProperties(HierarchyChild::class);

        // Order proves leaf-first: child's own properties come before
        // parent's. The declaring-class filter is what stops the parent
        // iteration from re-emitting the child-only property.
        self::assertSame(
            [
                ['class' => HierarchyChild::class, 'property' => 'shared'],
                ['class' => HierarchyChild::class, 'property' => 'childOnly'],
                ['class' => HierarchyParent::class, 'property' => 'parentOnly'],
            ],
            $visited,
        );
    }

    public function testRedeclaredPropertyIsCollectedOnceFromTheChild(): void
    {
        $walker = new HierarchyWalker();

        $visited = $walker->visitedProperties(HierarchyChild::class);

        $sharedHits = array_values(array_filter(
            $visited,
            static fn (array $entry): bool => $entry['property'] === 'shared',
        ));

        self::assertCount(1, $sharedHits);
        self::assertSame(HierarchyChild::class, $sharedHits[0]['class']);
    }

    public function testWalkIsIdempotentAcrossCalls(): void
    {
        $walker = new HierarchyWalker();

        $first = $walker->visitedProperties(HierarchyChild::class);
        $second = $walker->visitedProperties(HierarchyChild::class);

        self::assertSame($first, $second);
    }
}
