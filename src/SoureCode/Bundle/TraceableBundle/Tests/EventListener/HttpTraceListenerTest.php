<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Ulid;

final class HttpTraceListenerTest extends TestCase
{
    public function testAdoptsValidIncomingHeaderWhenAcceptingAlways(): void
    {
        $incoming = new Ulid();
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id', 'always');

        $listener->onRequest($this->makeRequestEvent((string) $incoming));

        self::assertNotNull($holder->getCurrent());
        self::assertTrue($incoming->equals($holder->getCurrent()->getId()));
    }

    public function testIgnoresIncomingHeaderByDefault(): void
    {
        $incoming = new Ulid();
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $listener->onRequest($this->makeRequestEvent((string) $incoming));

        self::assertNotNull($holder->getCurrent(), 'Listener still creates a fresh context');
        self::assertFalse($incoming->equals($holder->getCurrent()->getId()), 'Default config must NOT trust external header');
    }

    public function testInvalidIncomingHeaderIsLoggedAndDiscarded(): void
    {
        $logger = $this->captureLogger();
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id', 'always', $logger);

        $listener->onRequest($this->makeRequestEvent('not-a-ulid'));

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('not-a-ulid', $logger->records[0]['context']['value']);
        self::assertNotNull($holder->getCurrent(), 'A fresh Ulid is still generated when the incoming value is invalid');
    }

    public function testMissingIncomingHeaderGeneratesFreshUlid(): void
    {
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $listener->onRequest($this->makeRequestEvent(null));

        self::assertNotNull($holder->getCurrent());
    }

    public function testSubRequestIsIgnoredOnRequest(): void
    {
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $request = Request::create('/');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $listener->onRequest($event);

        self::assertNull($holder->getCurrent());
    }

    public function testNullRequestHeaderConfigSkipsLookup(): void
    {
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, null, 'X-Request-Id');

        $listener->onRequest($this->makeRequestEvent('not-a-ulid'));

        self::assertNotNull($holder->getCurrent(), 'Listener still creates a fresh context when header lookup is disabled');
    }

    public function testResponseEchoesActiveUlid(): void
    {
        $context = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($context);

        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $response = new Response();
        $listener->onResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        self::assertSame((string) $context->getId(), $response->headers->get('X-Request-Id'));
    }

    public function testResponseHeaderNullConfigSkipsEcho(): void
    {
        $context = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($context);

        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', null);

        $response = new Response();
        $listener->onResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        self::assertFalse($response->headers->has('X-Request-Id'));
    }

    public function testResponseDoesNothingWithoutActiveContext(): void
    {
        $holder = new TraceContextHolder();
        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $response = new Response();
        $listener->onResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        self::assertFalse($response->headers->has('X-Request-Id'));
    }

    public function testSubRequestIsIgnoredOnResponse(): void
    {
        $context = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($context);

        $listener = new HttpTraceListener(new TraceContextFactory(), $holder, 'X-Request-Id', 'X-Request-Id');

        $response = new Response();
        $listener->onResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::SUB_REQUEST,
            $response,
        ));

        self::assertFalse($response->headers->has('X-Request-Id'));
    }

    private function makeRequestEvent(?string $headerValue): RequestEvent
    {
        $request = Request::create('/');

        if ($headerValue !== null) {
            $request->headers->set('X-Request-Id', $headerValue);
        }

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
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
