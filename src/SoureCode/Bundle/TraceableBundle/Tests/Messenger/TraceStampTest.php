<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

final class TraceStampTest extends TestCase
{
    public function testStampHoldsTheProvidedUlid(): void
    {
        $id = new Ulid();
        $stamp = new TraceStamp($id);

        self::assertSame($id, $stamp->id);
    }

    public function testStampImplementsMessengerStampInterface(): void
    {
        self::assertInstanceOf(StampInterface::class, new TraceStamp(new Ulid()));
    }
}
