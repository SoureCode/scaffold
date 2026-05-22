<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Lock;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\Lock\TracingLock;
use SoureCode\Bundle\TraceableBundle\Lock\TracingLockFactory;
use SoureCode\Bundle\TraceableBundle\Tests\Support\InMemoryLogger;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Uid\Ulid;

final class TracingLockFactoryTest extends TestCase
{
    public function testCreateLockReturnsTracingDecoratorAndLogsThroughIt(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();
        $holder->setCurrent(new TraceContext(new Ulid()));

        $factory = new TracingLockFactory(
            new LockFactory(new InMemoryStore()),
            $holder,
            $logger,
        );

        $lock = $factory->createLock('cache.prune');

        self::assertInstanceOf(TracingLock::class, $lock);
        self::assertTrue($lock->acquire());
        self::assertNotEmpty($logger->records);
        self::assertSame('cache.prune', $logger->records[0]['context']['resource']);
    }

    public function testCreateLockFromKeyReturnsTracingDecorator(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();

        $factory = new TracingLockFactory(
            new LockFactory(new InMemoryStore()),
            $holder,
            $logger,
        );

        $lock = $factory->createLockFromKey(new Key('cache.prune'));

        self::assertInstanceOf(TracingLock::class, $lock);
    }
}
