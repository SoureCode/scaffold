<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Tests\Baggage;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Traceable\Baggage\Baggage;

final class BaggageTest extends TestCase
{
    public function testWithAndGet(): void
    {
        $baggage = (new Baggage())->with('user_id', '42')->with('tenant', 'acme');

        self::assertSame('42', $baggage->get('user_id'));
        self::assertSame('acme', $baggage->get('tenant'));
    }

    public function testHeaderRoundTrip(): void
    {
        $original = new Baggage(['user_id' => '42', 'tenant' => 'acme/test']);

        $parsed = Baggage::fromHeader($original->toHeader());

        self::assertSame('42', $parsed->get('user_id'));
        self::assertSame('acme/test', $parsed->get('tenant'));
    }

    public function testHeaderRejectsMalformedSegments(): void
    {
        $baggage = Baggage::fromHeader('user_id=42, malformed-segment, tenant=acme');

        self::assertSame('42', $baggage->get('user_id'));
        self::assertSame('acme', $baggage->get('tenant'));
        self::assertNull($baggage->get('malformed-segment'));
    }

    public function testWithoutRemovesKey(): void
    {
        $baggage = (new Baggage(['x' => '1']))->without('x');

        self::assertNull($baggage->get('x'));
    }
}
