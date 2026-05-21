<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Baggage;

/**
 * W3C Baggage value object — flat string→string map propagated alongside
 * the trace id. Encoding follows the "baggage" header from the W3C draft:
 *
 *     key=value, key=value, …
 *
 * Names and values are percent-encoded. Values containing "=" or "," round-trip
 * cleanly via {@see toHeader()} / {@see fromHeader()}.
 */
final class Baggage
{
    /**
     * @param array<string, string> $items
     */
    public function __construct(
        public readonly array $items = [],
    ) {
    }

    public function with(string $key, string $value): self
    {
        return new self([...$this->items, $key => $value]);
    }

    public function without(string $key): self
    {
        $items = $this->items;
        unset($items[$key]);

        return new self($items);
    }

    public function get(string $key): ?string
    {
        return $this->items[$key] ?? null;
    }

    public function toHeader(): string
    {
        $parts = [];

        foreach ($this->items as $key => $value) {
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode(',', $parts);
    }

    public static function fromHeader(string $header): self
    {
        $items = [];

        foreach (explode(',', $header) as $pair) {
            $pair = trim($pair);

            if ($pair === '') {
                continue;
            }

            $eq = strpos($pair, '=');

            if ($eq === false) {
                continue;
            }

            $key = rawurldecode(substr($pair, 0, $eq));
            $value = rawurldecode(substr($pair, $eq + 1));

            if ($key === '') {
                continue;
            }

            $items[$key] = $value;
        }

        return new self($items);
    }
}
