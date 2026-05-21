<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Validation\SettingsSchemaInterface;

/**
 * Decorator that consults a {@see SettingsSchemaInterface} before delegating
 * writes to the inner manager. Reads pass through unchanged.
 */
final class ValidatingSettingsManager extends AbstractSettingsManager
{
    public function __construct(
        private readonly SettingsManagerInterface $inner,
        private readonly SettingsSchemaInterface $schema,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->schema->validate($key, $value);
        $this->inner->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($key);
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }
}
