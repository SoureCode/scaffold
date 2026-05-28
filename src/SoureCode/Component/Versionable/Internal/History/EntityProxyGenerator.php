<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal\History;

use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * @internal Produces PHP source for an entity-level Versionable proxy:
 *           a subclass of the user's entity that adds one
 *           `get<Field>History()` method per single-valued versioned
 *           relation whose target is itself versioned. Each method
 *           delegates to {@see HistoryRegistry::historyFor()}.
 */
final class EntityProxyGenerator
{
    public function __construct(
        private readonly VersionableMetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    public function shouldGenerate(ClassMetadata $classMetadata): bool
    {
        $originalClass = $classMetadata->getName();

        // Skip our own generated proxy/history classes so a re-trigger of
        // loadClassMetadata on the subclass does not recurse into another
        // proxy generation pass.
        if (str_starts_with($originalClass, EntityProxyNamer::NAMESPACE_PREFIX)) {
            return false;
        }

        if (str_starts_with($originalClass, HistoryClassNamer::NAMESPACE_PREFIX)) {
            return false;
        }

        if (!$this->metadataFactory->isVersionable($originalClass)) {
            return false;
        }

        $metadata = $this->metadataFactory->getMetadataFor($originalClass);

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if (!$classMetadata->isSingleValuedAssociation($fieldName)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($fieldName);

            if (!$assoc->isOwningSide()) {
                continue;
            }

            if ($this->metadataFactory->isVersionable($assoc->targetEntity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    public function generate(ClassMetadata $classMetadata): string
    {
        $originalClass = $classMetadata->getName();
        $metadata = $this->metadataFactory->getMetadataFor($originalClass);

        $proxyFqcn = EntityProxyNamer::proxyClassFor($originalClass);
        $lastSeparator = strrpos($proxyFqcn, '\\');
        $proxyNamespace = substr($proxyFqcn, 0, (int) $lastSeparator);
        $proxyShortName = substr($proxyFqcn, (int) $lastSeparator + 1);

        $registryClass = '\\' . HistoryRegistry::class;
        $methods = [];

        foreach ($metadata->bindings as $binding) {
            $fieldName = $binding->property->getName();

            if (!$classMetadata->hasAssociation($fieldName)) {
                continue;
            }

            if (!$classMetadata->isSingleValuedAssociation($fieldName)) {
                continue;
            }

            $assoc = $classMetadata->getAssociationMapping($fieldName);

            if (!$assoc->isOwningSide()) {
                continue;
            }

            if (!$this->metadataFactory->isVersionable($assoc->targetEntity)) {
                continue;
            }

            $historyClass = HistoryClassNamer::historyClassFor($assoc->targetEntity);
            $methodName = 'get' . ucfirst($fieldName) . 'History';

            $methods[] = <<<PHP
                    public function {$methodName}(): ?\\{$historyClass}
                    {
                        return {$registryClass}::historyFor(\$this, '{$fieldName}');
                    }
                PHP;
        }

        $methodsRendered = implode("\n\n", $methods);
        $parentClass = '\\' . ltrim($originalClass, '\\');

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$proxyNamespace};

            class {$proxyShortName} extends {$parentClass}
            {
            {$methodsRendered}
            }

            PHP;
    }
}
