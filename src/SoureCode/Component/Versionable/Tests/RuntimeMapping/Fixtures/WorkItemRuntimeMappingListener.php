<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

final class WorkItemRuntimeMappingListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();

        if ($classMetadata->getName() !== WorkItem::class) {
            return;
        }

        $classMetadata->mapManyToOne([
            'fieldName' => 'createdBy',
            'targetEntity' => PlainUser::class,
            'joinColumns' => [[
                'name' => 'created_by_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
            ]],
        ]);
    }
}
