<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Metadata\VersionedBinding;

final class VersionableSchemaListener
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();
        $entityManager = $args->getEntityManager();
        $metadataFactoryDoctrine = $entityManager->getMetadataFactory();

        foreach ($metadataFactoryDoctrine->getAllMetadata() as $classMetadata) {
            $metadata = $this->metadataFactory->getMetadataFor($classMetadata->getName());

            if ($metadata->isEmpty()) {
                continue;
            }

            $this->createVersionTable($schema, $classMetadata, $metadata->bindings, $metadataFactoryDoctrine);
        }
    }

    /**
     * @param list<VersionedBinding> $bindings
     */
    private function createVersionTable(
        Schema $schema,
        ClassMetadata $sourceMetadata,
        array $bindings,
        \Doctrine\Persistence\Mapping\ClassMetadataFactory $doctrineFactory,
    ): void {
        $sourceTable = $sourceMetadata->getTableName();
        $versionTableName = $sourceTable . '_version';

        if ($schema->hasTable($versionTableName)) {
            return;
        }

        $versionTable = $schema->createTable($versionTableName);

        $versionTable->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $versionTable->setPrimaryKey(['id']);

        $sourceIdField = $sourceMetadata->getSingleIdentifierFieldName();
        $sourceIdMapping = $sourceMetadata->getFieldMapping($sourceIdField);

        $versionTable->addColumn('entity_id', $sourceIdMapping->type, ['notnull' => true]);
        $versionTable->addForeignKeyConstraint(
            $sourceTable,
            ['entity_id'],
            [$sourceMetadata->getColumnName($sourceIdField)],
            ['onDelete' => 'CASCADE'],
        );
        $versionTable->addIndex(['entity_id']);

        $versionTable->addColumn('version', Types::INTEGER, ['notnull' => true]);
        $versionTable->addUniqueIndex(['entity_id', 'version']);

        $versionTable->addColumn('created_at', Types::DATETIMETZ_IMMUTABLE, ['notnull' => true]);

        foreach ($bindings as $binding) {
            $fieldName = $binding->property->getName();

            if ($sourceMetadata->hasField($fieldName)) {
                $this->addScalarFieldColumn($versionTable, $sourceMetadata, $fieldName);

                continue;
            }

            if (!$sourceMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if ($sourceMetadata->isSingleValuedAssociation($fieldName)) {
                $this->addSingleAssociationColumn($versionTable, $sourceMetadata, $fieldName, $doctrineFactory);

                continue;
            }

            if ($sourceMetadata->isCollectionValuedAssociation($fieldName)) {
                $this->createCollectionTable($schema, $versionTableName, $sourceMetadata, $fieldName, $doctrineFactory);
            }
        }
    }

    /**
     * @param class-string $targetClass
     */
    private function isTargetVersionable(string $targetClass): bool
    {
        return !$this->metadataFactory->getMetadataFor($targetClass)->isEmpty();
    }

    private function addScalarFieldColumn(\Doctrine\DBAL\Schema\Table $table, ClassMetadata $source, string $fieldName): void
    {
        $sourceField = $source->getFieldMapping($fieldName);
        $columnName = $source->getColumnName($fieldName);

        $options = ['notnull' => !($sourceField->nullable ?? false)];

        if (isset($sourceField->length)) {
            $options['length'] = $sourceField->length;
        }
        if (isset($sourceField->precision)) {
            $options['precision'] = $sourceField->precision;
        }
        if (isset($sourceField->scale)) {
            $options['scale'] = $sourceField->scale;
        }
        if (isset($sourceField->enumType)) {
            $options['platformOptions'] = ['enumType' => $sourceField->enumType];
        }

        $table->addColumn($columnName, $sourceField->type, $options);
    }

    private function addSingleAssociationColumn(
        \Doctrine\DBAL\Schema\Table $table,
        ClassMetadata $source,
        string $fieldName,
        \Doctrine\Persistence\Mapping\ClassMetadataFactory $factory,
    ): void {
        $assoc = $source->getAssociationMapping($fieldName);
        $targetMetadata = $factory->getMetadataFor($assoc->targetEntity);
        $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
        $targetIdType = $targetMetadata->getFieldMapping($targetIdField)->type;

        $columnName = $fieldName . '_id';

        $table->addColumn($columnName, $targetIdType, ['notnull' => false]);
        $table->addIndex([$columnName]);

        if ($this->isTargetVersionable($assoc->targetEntity)) {
            $table->addColumn($fieldName . '_version', Types::INTEGER, ['notnull' => false]);
        }
    }

    private function createCollectionTable(
        Schema $schema,
        string $versionTableName,
        ClassMetadata $source,
        string $fieldName,
        \Doctrine\Persistence\Mapping\ClassMetadataFactory $factory,
    ): void {
        $assoc = $source->getAssociationMapping($fieldName);
        $targetMetadata = $factory->getMetadataFor($assoc->targetEntity);
        $targetIdField = $targetMetadata->getSingleIdentifierFieldName();
        $targetIdType = $targetMetadata->getFieldMapping($targetIdField)->type;

        $joinTableName = $versionTableName . '_' . $fieldName;

        if ($schema->hasTable($joinTableName)) {
            return;
        }

        $joinTable = $schema->createTable($joinTableName);
        $joinTable->addColumn('version_id', Types::INTEGER, ['notnull' => true]);
        $joinTable->addColumn('target_id', $targetIdType, ['notnull' => true]);
        $joinTable->setPrimaryKey(['version_id', 'target_id']);
        $joinTable->addForeignKeyConstraint($versionTableName, ['version_id'], ['id'], ['onDelete' => 'CASCADE']);
        $joinTable->addIndex(['target_id']);

        if ($this->isTargetVersionable($assoc->targetEntity)) {
            $joinTable->addColumn('target_version', Types::INTEGER, ['notnull' => false]);
        }
    }
}
