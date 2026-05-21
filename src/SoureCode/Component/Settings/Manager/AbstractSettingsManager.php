<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

abstract class AbstractSettingsManager implements SettingsManagerInterface
{
    protected const string KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]*$/';

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (!is_string($value)) {
            throw self::typeMismatch($key, 'string', $value);
        }

        return $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (!is_int($value)) {
            throw self::typeMismatch($key, 'int', $value);
        }

        return $value;
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (!is_bool($value)) {
            throw self::typeMismatch($key, 'bool', $value);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed>|null $default
     *
     * @return array<array-key, mixed>|null
     */
    public function getArray(string $key, ?array $default = null): ?array
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (!is_array($value)) {
            throw self::typeMismatch($key, 'array', $value);
        }

        return $value;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    protected static function validateKey(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid settings key "%s": must match %s.',
                $key,
                self::KEY_PATTERN,
            ));
        }
    }

    private static function typeMismatch(string $key, string $expected, mixed $actual): \UnexpectedValueException
    {
        return new \UnexpectedValueException(\sprintf(
            'Settings key "%s" was expected to be %s, got %s.',
            $key,
            $expected,
            get_debug_type($actual),
        ));
    }
}
