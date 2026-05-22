<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Validation;

/**
 * Simple in-memory schema where each known key declares a PHP type
 * (the same names you'd write in a declare statement: "string", "int",
 * "bool", "float", "array", "null") and an optional validator callable.
 *
 * Validator callable contract: throw `\InvalidArgumentException` (or any
 * subclass) to reject a value. The schema does NOT trap thrown
 * exceptions, so a `RuntimeException` or other type will propagate to the
 * caller of `SettingsManager::set()` unchanged. Callers that want a
 * uniform error type should wrap their validators themselves.
 *
 * @phpstan-type Rule array{type?: string, validator?: callable(mixed):void, required?: bool}
 */
final class ArraySettingsSchema implements SettingsSchemaInterface
{
    /**
     * @param array<string, Rule> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    public function isKnown(string $key): bool
    {
        return array_key_exists($key, $this->rules);
    }

    public function validate(string $key, mixed $value): void
    {
        if (!$this->isKnown($key)) {
            return;
        }

        $rule = $this->rules[$key];

        if ($value === null) {
            if (($rule['required'] ?? false) === true) {
                throw new \InvalidArgumentException(\sprintf('Settings key "%s" cannot be null.', $key));
            }

            return;
        }

        if (isset($rule['type'])) {
            $actual = get_debug_type($value);

            if (!self::typeMatches($actual, $rule['type'])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Settings key "%s" expects %s, got %s.',
                    $key,
                    $rule['type'],
                    $actual,
                ));
            }
        }

        if (isset($rule['validator'])) {
            ($rule['validator'])($value);
        }
    }

    private static function typeMatches(string $actual, string $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }

        // get_debug_type returns "array" for sequential too; "int" not "integer"; align with declarations.
        return match ($expected) {
            'integer' => $actual === 'int',
            'boolean' => $actual === 'bool',
            'double' => $actual === 'float',
            default => false,
        };
    }
}
