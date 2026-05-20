<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

final class FeatureFlagMappingDriver implements MappingDriver
{
    /**
     * @param class-string<FeatureFlagInterface> $entityClass
     */
    public function __construct(
        private readonly string $entityClass = FeatureFlag::class,
        private readonly string $tableName = 'feature_flags',
    ) {}

    public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
    {
        if ($className !== $this->entityClass) {
            return;
        }

        \assert($metadata instanceof OrmClassMetadata);

        $metadata->setPrimaryTable(['name' => $this->tableName]);

        $metadata->mapField([
            'fieldName' => 'name',
            'type' => 'string',
            'id' => true,
        ]);

        $metadata->mapField([
            'fieldName' => 'enabled',
            'type' => 'boolean',
        ]);
    }

    /**
     * @return list<class-string>
     */
    public function getAllClassNames(): array
    {
        return [$this->entityClass];
    }

    public function isTransient(string $className): bool
    {
        return $className !== $this->entityClass;
    }
}
