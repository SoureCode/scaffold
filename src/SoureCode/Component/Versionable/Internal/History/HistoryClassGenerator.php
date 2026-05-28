<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Produces PHP source for a *History class: a read-only DTO with
 *           one constructor-promoted property per versioned field — scalar
 *           and embedded values plus single-valued and collection-valued
 *           associations that resolve into the partner's *History (phase 5).
 *
 *           Phase 5 introduces the transitive walk: a versioned association
 *           on the source entity becomes a `?PartnerHistory` (single) or
 *           `array<PartnerHistory>` (collection) property on the parent
 *           *History, populated by {@see HistoryHydrator} from the snapshot
 *           row's `<field>_id`/`<field>_version` columns.
 */
final class HistoryClassGenerator
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string $originalClass
     */
    public function generate(string $originalClass): string
    {
        $classMetadata = $this->entityManager->getClassMetadata($originalClass);
        $metadata = $this->metadataFactory->getMetadataFor($originalClass);
        $reflection = new \ReflectionClass($originalClass);

        $historyFqcn = HistoryClassNamer::historyClassFor($originalClass);
        $lastSeparator = strrpos($historyFqcn, '\\');
        $historyNamespace = substr($historyFqcn, 0, (int) $lastSeparator);
        $historyShortName = substr($historyFqcn, (int) $lastSeparator + 1);

        $properties = [];
        $getters = [];

        $idField = $classMetadata->getSingleIdentifierFieldName();
        $idTypeDecl = $this->readPhpType($reflection, $idField);
        $properties[] = $this->renderConstructorProperty('id', $idTypeDecl);
        $getters[] = $this->renderGetter('Id', 'id', $idTypeDecl);

        if ($metadata->versionField === null) {
            throw new \RuntimeException(\sprintf('Versioned entity %s must declare a #[Version] property.', $originalClass));
        }

        $properties[] = $this->renderConstructorProperty('version', 'int');
        $getters[] = $this->renderGetter('Version', 'version', 'int');

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (isset($classMetadata->embeddedClasses[$fieldName])) {
                $embeddedClass = $classMetadata->embeddedClasses[$fieldName]['class'];
                $type = '\\' . ltrim($embeddedClass, '\\');
                $properties[] = $this->renderConstructorProperty($fieldName, $type);
                $getters[] = $this->renderGetter(ucfirst($fieldName), $fieldName, $type);

                continue;
            }

            if (isset($classMetadata->fieldMappings[$fieldName])) {
                $type = $this->readPhpType($reflection, $fieldName);
                $properties[] = $this->renderConstructorProperty($fieldName, $type);
                $getters[] = $this->renderGetter(ucfirst($fieldName), $fieldName, $type);

                continue;
            }

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($fieldName);
            $targetClass = $assoc->targetEntity;
            $targetIsVersioned = $this->metadataFactory->isVersionable($targetClass);

            $partnerType = $targetIsVersioned
                ? '\\' . HistoryClassNamer::historyClassFor($targetClass)
                : '\\' . ltrim($targetClass, '\\');

            if ($classMetadata->isSingleValuedAssociation($fieldName)) {
                $properties[] = $this->renderConstructorProperty($fieldName, '?' . $partnerType);
                $getters[] = $this->renderGetter(ucfirst($fieldName), $fieldName, '?' . $partnerType);

                continue;
            }

            if ($classMetadata->isCollectionValuedAssociation($fieldName)) {
                $properties[] = $this->renderConstructorCollectionProperty($fieldName, $partnerType);
                $getters[] = $this->renderCollectionGetter(ucfirst($fieldName), $fieldName, $partnerType);
            }
        }

        return $this->renderClass($historyNamespace, $historyShortName, $properties, $getters);
    }

    private function readPhpType(\ReflectionClass $reflection, string $propertyName): string
    {
        $current = $reflection;

        while ($current !== false && !$current->hasProperty($propertyName)) {
            $current = $current->getParentClass();
        }

        if ($current === false) {
            return 'mixed';
        }

        $property = $current->getProperty($propertyName);
        $type = $property->getType();

        if ($type === null) {
            return 'mixed';
        }

        if (!$type instanceof \ReflectionNamedType) {
            return 'mixed';
        }

        $name = $type->getName();
        $nullablePrefix = $type->allowsNull() && $name !== 'mixed' ? '?' : '';
        $rendered = $type->isBuiltin() ? $name : '\\' . $name;

        return $nullablePrefix . $rendered;
    }

    private function renderConstructorProperty(string $fieldName, string $type): string
    {
        return \sprintf('private readonly %s $%s', $type, $fieldName);
    }

    private function renderConstructorCollectionProperty(string $fieldName, string $partnerHistory): string
    {
        return \sprintf("/** @var list<%s> */\n        private readonly array \$%s", $partnerHistory, $fieldName);
    }

    private function renderGetter(string $methodSuffix, string $fieldName, string $type): string
    {
        return \sprintf("    public function get%s(): %s\n    {\n        return \$this->%s;\n    }", $methodSuffix, $type, $fieldName);
    }

    private function renderCollectionGetter(string $methodSuffix, string $fieldName, string $partnerHistory): string
    {
        return \sprintf(
            "    /** @return list<%s> */\n    public function get%s(): array\n    {\n        return \$this->%s;\n    }",
            $partnerHistory,
            $methodSuffix,
            $fieldName,
        );
    }

    /**
     * @param list<string> $properties
     * @param list<string> $getters
     */
    private function renderClass(string $namespace, string $shortName, array $properties, array $getters): string
    {
        $promotedList = implode(",\n        ", $properties);
        $gettersList = implode("\n\n", $getters);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            final class {$shortName}
            {
                public function __construct(
                    {$promotedList},
                ) {
                }

            {$gettersList}
            }

            PHP;
    }
}
