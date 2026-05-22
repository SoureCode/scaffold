<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Lock;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\Lock\TracingLock;
use SoureCode\Bundle\TraceableBundle\Tests\Support\InMemoryLogger;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Uid\Ulid;

final class TracingLockTest extends TestCase
{
    public function testAcquireLogsResultAndTraceId(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();
        $trace = new TraceContext(new Ulid());
        $holder->setCurrent($trace);

        $tracing = new TracingLock(
            new Lock(new Key('cache.prune'), new InMemoryStore()),
            'cache.prune',
            $holder,
            $logger,
        );

        $acquired = $tracing->acquire();

        self::assertTrue($acquired);
        self::assertNotEmpty($logger->records);
        $acquireLog = $logger->records[0];
        self::assertSame('info', $acquireLog['level']);
        self::assertSame('cache.prune', $acquireLog['context']['resource']);
        self::assertSame('ok', $acquireLog['context']['result']);
        self::assertSame((string) $trace->getId(), $acquireLog['context']['trace_id']);
    }

    public function testReleaseLogsTraceId(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();
        $trace = new TraceContext(new Ulid());
        $holder->setCurrent($trace);

        $tracing = new TracingLock(
            new Lock(new Key('cache.prune'), new InMemoryStore()),
            'cache.prune',
            $holder,
            $logger,
        );

        $tracing->acquire();
        $logger->records = [];

        $tracing->release();

        self::assertCount(1, $logger->records);
        self::assertSame('info', $logger->records[0]['level']);
        self::assertStringContainsString('lock.release', $logger->records[0]['message']);
        self::assertSame((string) $trace->getId(), $logger->records[0]['context']['trace_id']);
    }

    public function testTraceIdIsNullWhenHolderIsEmpty(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();

        $tracing = new TracingLock(
            new Lock(new Key('cache.prune'), new InMemoryStore()),
            'cache.prune',
            $holder,
            $logger,
        );

        $tracing->acquire();

        self::assertNull($logger->records[0]['context']['trace_id']);
    }

    public function testIsAcquiredReflectsInnerState(): void
    {
        $logger = new InMemoryLogger();
        $holder = new TraceContextHolder();

        $tracing = new TracingLock(
            new Lock(new Key('cache.prune'), new InMemoryStore()),
            'cache.prune',
            $holder,
            $logger,
        );

        self::assertFalse($tracing->isAcquired());
        $tracing->acquire();
        self::assertTrue($tracing->isAcquired());
    }
}
