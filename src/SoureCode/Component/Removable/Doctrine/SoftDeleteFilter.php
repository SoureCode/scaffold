<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;

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

                $columnName = $targetEntity->hasField($property->getName())
                    ? $targetEntity->getColumnName($property->getName())
                    : $property->getName();

                return $this->columnCache[$name] = $columnName;
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->columnCache[$name] = null;
    }
}
