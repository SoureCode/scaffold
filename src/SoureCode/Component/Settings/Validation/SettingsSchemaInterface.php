<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Validation;

/**
 * Decides whether a setting key/value pair is acceptable. Implementations
 * can be backed by an array, a JSON Schema, Symfony Validator, etc.
 *
 * Returning null means "this key has no schema, the manager should accept it".
 */
interface SettingsSchemaInterface
{
    public function isKnown(string $key): bool;

    /**
     * @throws \InvalidArgumentException when $value is not acceptable for $key
     */
    public function validate(string $key, mixed $value): void;
}
