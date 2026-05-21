<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceStamp;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

final class PropagationTest extends TestCase
{
    public function testIncomingHttpUlidFlowsThroughDispatchedMessageBackToReceivedHandler(): void
    {
        $factory = new TraceContextFactory();
        $holder = new TraceContextHolder();

        $httpListener = new HttpTraceListener($factory, $holder, 'X-Request-Id', 'X-Request-Id', 'always');
        $middleware = new TraceContextMiddleware($factory, $holder);

        $incoming = new Ulid();
        $request = Request::create('/');
        $request->headers->set('X-Request-Id', (string) $incoming);

        $httpListener->onRequest(new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        $afterHttp = $holder->getCurrent();
        self::assertNotNull($afterHttp);
        self::assertTrue($incoming->equals($afterHttp->getId()), 'HTTP listener should adopt the incoming Ulid');

        $dispatched = $middleware->handle(new Envelope(new \stdClass()), new StackMiddleware());

        $stamp = $dispatched->last(TraceStamp::class);
        self::assertNotNull($stamp, 'Dispatch path must attach a TraceStamp');
        self::assertTrue($incoming->equals($stamp->id), 'TraceStamp must carry the active Ulid');

        $holder->setCurrent(null);

        $middleware->handle(
            $dispatched->with(new ReceivedStamp('test-receiver')),
            new StackMiddleware(),
        );

        $afterReceive = $holder->getCurrent();
        self::assertNotNull($afterReceive, 'Receive path must restore the holder');
        self::assertTrue(
            $incoming->equals($afterReceive->getId()),
            'Received TraceStamp must restore the original Ulid in the holder',
        );
    }
}
