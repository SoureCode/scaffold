<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ToOneAssociationMapping;

final class ColumnNamer
{
    private function __construct()
    {
    }

    /**
     * The FK column name for a single-valued association — Doctrine's own
     * joinColumn name from the association mapping. Matches the live owning
     * table column verbatim; reused as the snapshot column name.
     */
    public static function singleAssociationId(AssociationMapping $association): string
    {
        if (!$association instanceof ToOneAssociationMapping) {
            throw new \LogicException(\sprintf('Association %s::%s is not single-valued.', $association->sourceEntity, $association->fieldName));
        }

        $joinColumns = $association->joinColumns;

        if ($joinColumns === []) {
            throw new \LogicException(\sprintf('Association %s::%s has no joinColumns.', $association->sourceEntity, $association->fieldName));
        }

        return $joinColumns[0]->name;
    }

    /**
     * The pin column name on the live owning table for a versioned target.
     * Derived from the joinColumn: `<name>_id` → `<name>_version`, otherwise
     * `<joinColumn>_version`. Doctrine owns the base name; we own the suffix.
     */
    public static function singleAssociationVersion(AssociationMapping $association): string
    {
        $joinColumnName = self::singleAssociationId($association);

        if (str_ends_with($joinColumnName, '_id')) {
            return substr($joinColumnName, 0, -3) . '_version';
        }

        return $joinColumnName . '_version';
    }
}
