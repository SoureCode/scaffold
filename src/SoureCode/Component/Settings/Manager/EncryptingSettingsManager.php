<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Manager;

use Doctrine\Common\Collections\Collection;
use SoureCode\Component\Settings\Encryption\SensitiveValueCoderInterface;

/**
 * Decorator that runs sensitive setting values through a coder before
 * persisting and reverses the transformation on read. Sensitivity is
 * decided by an exact key match against the configured list — wildcards
 * are not supported here on purpose; if you need globs, compose this
 * decorator with a custom matcher.
 *
 * Stored values are tagged with a short prefix so the decoder can refuse
 * to operate on legacy plaintext rows without dropping data silently.
 */
final class EncryptingSettingsManager extends AbstractSettingsManager
{
    private const string ENCODED_PREFIX = 'enc::';

    /**
     * @param list<string> $sensitiveKeys
     */
    public function __construct(
        private readonly SettingsManagerInterface $inner,
        private readonly SensitiveValueCoderInterface $coder,
        private readonly array $sensitiveKeys,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->inner->get($key, null);

        if ($raw === null) {
            return $default;
        }

        if (!$this->isSensitive($key)) {
            return $raw;
        }

        if (!is_string($raw) || !str_starts_with($raw, self::ENCODED_PREFIX)) {
            throw new \RuntimeException(\sprintf(
                'Settings: sensitive key "%s" is not in the expected encrypted format. ' .
                'Re-encrypt the row or remove it from the sensitive_keys list.',
                $key,
            ));
        }

        return $this->coder->decode(substr($raw, strlen(self::ENCODED_PREFIX)));
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->isSensitive($key)) {
            $value = self::ENCODED_PREFIX . $this->coder->encode($value);
        }

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

    private function isSensitive(string $key): bool
    {
        return in_array($key, $this->sensitiveKeys, true);
    }
}
