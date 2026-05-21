<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\W3C;

/**
 * W3C Trace Context "traceparent" header value (RFC 9162 / level-1):
 *
 *     <version>-<trace-id 32 hex>-<parent-id 16 hex>-<flags 2 hex>
 *
 * Only version "00" is recognised here — the field tolerates higher
 * versions but consumers must ignore them per the spec; we follow that rule
 * by failing to parse them.
 */
final class Traceparent
{
    public const string VERSION = '00';

    public const int FLAG_SAMPLED = 0x01;

    private function __construct(
        public readonly string $traceId,
        public readonly string $parentId,
        public readonly int $flags,
    ) {
    }

    public static function parse(string $value): ?self
    {
        $parts = explode('-', trim($value));

        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId, $parentId, $flags] = $parts;

        if ($version !== self::VERSION) {
            return null;
        }

        if (!self::isHexOfLength($traceId, 32) || $traceId === str_repeat('0', 32)) {
            return null;
        }

        if (!self::isHexOfLength($parentId, 16) || $parentId === str_repeat('0', 16)) {
            return null;
        }

        if (!self::isHexOfLength($flags, 2)) {
            return null;
        }

        return new self($traceId, $parentId, hexdec($flags));
    }

    public static function generate(int $flags = 0): self
    {
        return new self(
            self::randomHex(16),
            self::randomHex(8),
            $flags,
        );
    }

    public function isSampled(): bool
    {
        return ($this->flags & self::FLAG_SAMPLED) !== 0;
    }

    public function __toString(): string
    {
        return \sprintf('%s-%s-%s-%02x', self::VERSION, $this->traceId, $this->parentId, $this->flags & 0xFF);
    }

    private static function isHexOfLength(string $value, int $length): bool
    {
        return strlen($value) === $length && ctype_xdigit($value);
    }

    private static function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
