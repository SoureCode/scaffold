<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Test stand-in for a real "mapping listener" (e.g.
 * {@see \SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener}).
 * Adds a `createdBy` ManyToOne to {@see Task} during loadClassMetadata
 * without declaring `#[ORM\ManyToOne]` on the property — the regression
 * scenario Versionable must handle.
 */
final class TaskRuntimeMappingListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();

        if ($classMetadata->getName() !== Task::class) {
            return;
        }

        $classMetadata->mapManyToOne([
            'fieldName' => 'createdBy',
            'targetEntity' => Partner::class,
            'joinColumns' => [[
                'name' => 'created_by_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
            ]],
        ]);
    }
}
