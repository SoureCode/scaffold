<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Encryption;

/**
 * Encodes / decodes setting values that should not be stored in the clear.
 * The host application supplies its own implementation (sodium, KMS, libsodium-aead, …).
 * The {@see EncryptingSettingsManager} decorator delegates here when a key
 * is marked as sensitive.
 */
interface SensitiveValueCoderInterface
{
    public function encode(mixed $value): string;

    public function decode(string $value): mixed;
}
