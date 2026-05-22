<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Encryption;

/**
 * Encodes / decodes setting values that should not be stored in the clear.
 * The host application supplies its own implementation (sodium, KMS, libsodium-aead, …).
 * The {@see EncryptingSettingsManager} decorator delegates here when a key
 * is marked as sensitive.
 *
 * The {@see scheme()} identifier is stored alongside the encoded payload so
 * a host that rotates schemes can deploy a coder that recognises both the
 * old and the new tag and migrates rows lazily on read — no global rewrite
 * pass needed.
 */
interface SensitiveValueCoderInterface
{
    /**
     * Stable, short identifier of the on-disk encoding scheme. Persisted
     * alongside the value so the decoder can reject mismatched payloads
     * instead of mis-decoding them. Treat as a versioned constant — once a
     * value is shipped on top of it, do not change it.
     */
    public function scheme(): string;

    public function encode(mixed $value): string;

    /**
     * Decode a payload previously emitted by `encode()` on a coder that
     * advertised the same scheme. Implementations decide whether to also
     * accept legacy schemes for read-only migration.
     */
    public function decode(string $value): mixed;
}
