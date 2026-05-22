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
 * Stored values are tagged with `enc::<scheme>:` so the decoder can:
 *   - refuse to operate on legacy plaintext rows without dropping data,
 *   - reject payloads that were written by a different scheme, and
 *   - let a multi-scheme coder migrate values lazily on read instead of
 *     forcing a global rewrite of every row.
 */
final class EncryptingSettingsManager extends AbstractSettingsManager
{
    public const string ENCODED_PREFIX = 'enc::';

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

        $remainder = substr($raw, strlen(self::ENCODED_PREFIX));
        $separator = strpos($remainder, ':');

        if ($separator === false) {
            throw new \RuntimeException(\sprintf(
                'Settings: sensitive key "%s" is missing the scheme tag — expected "%s<scheme>:<payload>".',
                $key,
                self::ENCODED_PREFIX,
            ));
        }

        $scheme = substr($remainder, 0, $separator);
        $payload = substr($remainder, $separator + 1);

        return $this->coder->decode($scheme === $this->coder->scheme() ? $payload : $remainder);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->isSensitive($key)) {
            $value = self::ENCODED_PREFIX . $this->coder->scheme() . ':' . $this->coder->encode($value);
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
