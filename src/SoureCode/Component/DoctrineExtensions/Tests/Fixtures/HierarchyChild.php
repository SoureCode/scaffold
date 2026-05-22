<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

/**
 * Plain-PHP fixture. The child declares one unique property and redeclares
 * the `shared` property from the parent — the walker must visit the
 * child's `shared` (leaf-first) and skip the parent's via the seen-dedup.
 */
class HierarchyChild extends HierarchyParent
{
    public string $shared = 'child-shared';

    public string $childOnly = 'c';
}
