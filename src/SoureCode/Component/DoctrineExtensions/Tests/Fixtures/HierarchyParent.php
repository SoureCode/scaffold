<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

/**
 * Plain-PHP fixture for {@see \SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory::walkHierarchy()}.
 * The parent declares one shared and one unique property.
 */
class HierarchyParent
{
    public string $shared = 'parent-shared';

    public string $parentOnly = 'p';
}
