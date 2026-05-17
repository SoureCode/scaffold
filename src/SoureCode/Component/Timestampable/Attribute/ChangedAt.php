<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Attribute;

use Doctrine\DBAL\Types\Types;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ChangedAt
{
    /**
     * @var list<string>
     */
    public readonly array $fields;

    /**
     * @param string|list<string> $field
     */
    public function __construct(
        string|array $field,
        public readonly bool $matchValue = false,
        public readonly mixed $value = null,
        public readonly string $type = Types::DATETIMETZ_IMMUTABLE,
    ) {
        $fields = is_array($field) ? array_values($field) : [$field];

        if ($fields === []) {
            throw new \InvalidArgumentException('ChangedAt requires at least one field.');
        }

        if ($matchValue && count($fields) !== 1) {
            throw new \InvalidArgumentException('ChangedAt value matcher requires exactly one field.');
        }

        $this->fields = $fields;
    }
}
