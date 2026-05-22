<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ChangedBy
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
    ) {
        $fields = is_array($field) ? array_values($field) : [$field];

        if ($fields === []) {
            throw new \InvalidArgumentException('ChangedBy requires at least one field.');
        }

        if ($matchValue && count($fields) !== 1) {
            throw new \InvalidArgumentException('ChangedBy value matcher requires exactly one field.');
        }

        $this->fields = $fields;
    }
}
