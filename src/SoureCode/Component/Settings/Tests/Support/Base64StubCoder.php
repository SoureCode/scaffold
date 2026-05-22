<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Support;

use SoureCode\Component\Settings\Encryption\SensitiveValueCoderInterface;

final class Base64StubCoder implements SensitiveValueCoderInterface
{
    public function scheme(): string
    {
        return 'b64';
    }

    public function encode(mixed $value): string
    {
        return base64_encode(serialize($value));
    }

    public function decode(string $value): mixed
    {
        return unserialize(base64_decode($value, true), ['allowed_classes' => false]);
    }
}
