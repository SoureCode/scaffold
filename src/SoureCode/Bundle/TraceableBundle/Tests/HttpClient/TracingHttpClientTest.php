<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TraceableBundle\HttpClient\TracingHttpClient;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Ulid;

final class TracingHttpClientTest extends TestCase
{
    public function testRequestAddsTraceHeaderWhenContextIsActive(): void
    {
        $trace = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($trace);

        $captured = null;
        $inner = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return new MockResponse('ok');
        });

        $client = new TracingHttpClient($inner, $holder);

        $client->request('GET', 'https://example.test/');

        self::assertNotNull($captured);
        self::assertContains('X-Request-Id: ' . (string) $trace->getId(), $captured['headers']);
    }

    public function testRequestDoesNotAddTraceHeaderWhenContextIsAbsent(): void
    {
        $holder = new TraceContextHolder();

        $captured = null;
        $inner = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return new MockResponse('ok');
        });

        $client = new TracingHttpClient($inner, $holder);

        $client->request('GET', 'https://example.test/');

        self::assertNotNull($captured);

        foreach ($captured['headers'] as $header) {
            self::assertStringNotContainsString('X-Request-Id', $header);
        }
    }

    public function testExistingTraceHeaderIsPreserved(): void
    {
        $trace = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($trace);

        $captured = null;
        $inner = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return new MockResponse('ok');
        });

        $client = new TracingHttpClient($inner, $holder);

        $client->request('GET', 'https://example.test/', [
            'headers' => ['X-Request-Id' => 'caller-supplied-id'],
        ]);

        self::assertNotNull($captured);
        self::assertContains('X-Request-Id: caller-supplied-id', $captured['headers']);
        self::assertNotContains('X-Request-Id: ' . (string) $trace->getId(), $captured['headers']);
    }

    public function testCustomHeaderNameIsHonoured(): void
    {
        $trace = new TraceContext(new Ulid());
        $holder = new TraceContextHolder();
        $holder->setCurrent($trace);

        $captured = null;
        $inner = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return new MockResponse('ok');
        });

        $client = new TracingHttpClient($inner, $holder, 'X-My-Trace');

        $client->request('GET', 'https://example.test/');

        self::assertNotNull($captured);
        self::assertContains('X-My-Trace: ' . (string) $trace->getId(), $captured['headers']);
    }
}
