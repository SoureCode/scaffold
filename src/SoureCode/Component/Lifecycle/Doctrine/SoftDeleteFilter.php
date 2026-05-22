<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;

/**
 * Hides soft-deleted rows from Doctrine queries by appending
 * "<alias>.<deleted_column> IS NULL" to every SELECT whose root entity
 * carries a #[DeletedAt] property.
 *
 * Detection cache: filters are constructed per EntityManager and re-used
 * for the lifetime of the request, so the per-class reflection lookup
 * happens at most once per entity.
 *
 * Disable per query: $entityManager->getFilters()->disable('soft_delete');
 */
final class SoftDeleteFilter extends SQLFilter
{
    /**
     * @var array<class-string, ?string>
     */
    private array $columnCache = [];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $column = $this->resolveDeletedAtColumn($targetEntity);

        if ($column === null) {
            return '';
        }

        return \sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }

    private function resolveDeletedAtColumn(ClassMetadata $targetEntity): ?string
    {
        $name = $targetEntity->getName();

        if (array_key_exists($name, $this->columnCache)) {
            return $this->columnCache[$name];
        }

        $reflection = new \ReflectionClass($name);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getAttributes(DeletedAt::class) === []) {
                    continue;
                }

                $propertyName = $property->getName();

                // No registered Doctrine field mapping means the property
                // is either embedded or otherwise not reachable as a flat
                // column. Filtering by the raw property name would emit
                // SQL referring to a column that does not exist, so treat
                // it as "not filterable".
                if (!isset($targetEntity->fieldMappings[$propertyName])) {
                    return $this->columnCache[$name] = null;
                }

                return $this->columnCache[$name] = $targetEntity->getColumnName($propertyName);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->columnCache[$name] = null;
    }
}
