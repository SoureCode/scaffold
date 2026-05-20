<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Model\SettingInterface;

final class SettingMappingDriver implements MappingDriver
{
    /**
     * @param class-string<SettingInterface> $entityClass
     */
    public function __construct(
        private readonly string $entityClass = Setting::class,
        private readonly string $tableName = 'settings',
    ) {}

    public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
    {
        if ($className !== $this->entityClass) {
            return;
        }

        \assert($metadata instanceof OrmClassMetadata);

        $metadata->setPrimaryTable(['name' => $this->tableName]);

        $metadata->mapField([
            'fieldName' => 'key',
            'type' => 'string',
            'id' => true,
            'columnName' => '`key`',
        ]);

        $metadata->mapField([
            'fieldName' => 'value',
            'type' => 'json',
            'nullable' => true,
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
