<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceStamp;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

final class TraceContextMiddlewareTest extends TestCase
{
    public function testReceivedEnvelopeWithStampRestoresHolder(): void
    {
        $id = new Ulid();
        $holder = new TraceContextHolder();
        $middleware = new TraceContextMiddleware(new TraceContextFactory(), $holder);

        $envelope = new Envelope(new \stdClass(), [
            new ReceivedStamp('test'),
            new TraceStamp($id),
        ]);

        $middleware->handle($envelope, new StackMiddleware());

        self::assertNotNull($holder->getCurrent());
        self::assertTrue($id->equals($holder->getCurrent()->getId()));
    }

    public function testReceivedEnvelopeWithoutStampLogsWarningAndGeneratesUlid(): void
    {
        $logger = $this->captureLogger();
        $holder = new TraceContextHolder();
        $middleware = new TraceContextMiddleware(new TraceContextFactory(), $holder, $logger);

        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('test')]);

        $middleware->handle($envelope, new StackMiddleware());

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame(\stdClass::class, $logger->records[0]['context']['message']);
        self::assertNotNull($holder->getCurrent(), 'A fresh Ulid is generated for the missing stamp');
    }

    public function testDispatchPathAttachesTraceStampFromActiveContext(): void
    {
        $id = new Ulid();
        $holder = new TraceContextHolder();
        $holder->setCurrent(new TraceContext($id));

        $middleware = new TraceContextMiddleware(new TraceContextFactory(), $holder);

        $result = $middleware->handle(new Envelope(new \stdClass()), new StackMiddleware());

        $stamp = $result->last(TraceStamp::class);
        self::assertNotNull($stamp);
        self::assertTrue($id->equals($stamp->id));
    }

    public function testDispatchPathDoesNotAttachWhenNoActiveContext(): void
    {
        $holder = new TraceContextHolder();
        $middleware = new TraceContextMiddleware(new TraceContextFactory(), $holder);

        $result = $middleware->handle(new Envelope(new \stdClass()), new StackMiddleware());

        self::assertNull($result->last(TraceStamp::class), 'Missing active context means no stamp added');
    }

    public function testDispatchPathPreservesExistingTraceStamp(): void
    {
        $original = new Ulid();
        $different = new Ulid();

        $holder = new TraceContextHolder();
        $holder->setCurrent(new TraceContext($different));

        $middleware = new TraceContextMiddleware(new TraceContextFactory(), $holder);

        $result = $middleware->handle(
            new Envelope(new \stdClass(), [new TraceStamp($original)]),
            new StackMiddleware(),
        );

        $stamp = $result->last(TraceStamp::class);
        self::assertNotNull($stamp);
        self::assertTrue($original->equals($stamp->id), 'An existing TraceStamp must be left alone, not overwritten');
    }

    /**
     * @return LoggerInterface&object{records: list<array{level: string, message: string, context: array<string, mixed>}>}
     */
    private function captureLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
