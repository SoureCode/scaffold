<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Tests\W3C;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Traceable\W3C\Traceparent;

final class TraceparentTest extends TestCase
{
    public function testParseValidHeaderRoundTrips(): void
    {
        $raw = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

        $parent = Traceparent::parse($raw);

        self::assertNotNull($parent);
        self::assertSame('0af7651916cd43dd8448eb211c80319c', $parent->traceId);
        self::assertSame('b7ad6b7169203331', $parent->parentId);
        self::assertTrue($parent->isSampled());
        self::assertSame($raw, (string) $parent);
    }

    public function testParseRejectsWrongVersion(): void
    {
        self::assertNull(Traceparent::parse('ff-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01'));
    }

    public function testParseRejectsAllZeroIds(): void
    {
        self::assertNull(Traceparent::parse('00-00000000000000000000000000000000-b7ad6b7169203331-01'));
        self::assertNull(Traceparent::parse('00-0af7651916cd43dd8448eb211c80319c-0000000000000000-01'));
    }

    public function testParseRejectsMalformedHex(): void
    {
        self::assertNull(Traceparent::parse('00-not-hex-at-all-not-hex-at-all-b7ad6b7169203331-01'));
    }

    public function testGenerateProducesValidPair(): void
    {
        $parent = Traceparent::generate(Traceparent::FLAG_SAMPLED);

        self::assertSame(32, strlen($parent->traceId));
        self::assertSame(16, strlen($parent->parentId));
        self::assertTrue($parent->isSampled());
        self::assertNotNull(Traceparent::parse((string) $parent));
    }
}
