<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\TraceableBundle\EventListener\ConsoleTraceListener;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Uid\Ulid;

final class ConsoleTraceListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TRACE_ID');
    }

    public function testGeneratesFreshUlidWhenEnvVarAbsent(): void
    {
        putenv('TRACE_ID');

        $holder = new TraceContextHolder();
        $listener = new ConsoleTraceListener(new TraceContextFactory(), $holder);

        $listener->onCommand($this->makeEvent());

        $context = $holder->getCurrent();
        self::assertNotNull($context);
    }

    public function testReusesValidIncomingUlidFromEnv(): void
    {
        $incoming = new Ulid();
        putenv('TRACE_ID=' . $incoming->toBase32());

        $holder = new TraceContextHolder();
        $listener = new ConsoleTraceListener(new TraceContextFactory(), $holder);

        $listener->onCommand($this->makeEvent());

        $context = $holder->getCurrent();
        self::assertNotNull($context);
        self::assertTrue($incoming->equals($context->getId()));
    }

    public function testInvalidEnvValueIsLoggedAndDiscarded(): void
    {
        putenv('TRACE_ID=not-a-ulid');

        $logger = $this->captureLogger();
        $holder = new TraceContextHolder();
        $listener = new ConsoleTraceListener(new TraceContextFactory(), $holder, 'TRACE_ID', $logger);

        $listener->onCommand($this->makeEvent());

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('not-a-ulid', $logger->records[0]['context']['value']);

        $context = $holder->getCurrent();
        self::assertNotNull($context);
    }

    public function testNullEnvVarConfigSkipsLookup(): void
    {
        putenv('TRACE_ID=' . (new Ulid())->toBase32());

        $holder = new TraceContextHolder();
        $listener = new ConsoleTraceListener(new TraceContextFactory(), $holder, null);

        $listener->onCommand($this->makeEvent());

        self::assertNotNull($holder->getCurrent());
    }

    private function makeEvent(): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command('trace:test'), new ArrayInput([]), new NullOutput());
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
