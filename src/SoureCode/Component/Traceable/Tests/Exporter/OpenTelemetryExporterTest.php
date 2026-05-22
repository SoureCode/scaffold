<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Tests\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use SoureCode\Component\Traceable\Exporter\OpenTelemetryExporter;
use SoureCode\Component\Traceable\TraceContext;
use Symfony\Component\Uid\Ulid;

final class OpenTelemetryExporterTest extends TestCase
{
    /**
     * The SDK-absent branch of the constructor is reachable only when
     * `open-telemetry/sdk` is not autoloaded. The toolkit installs it in
     * dev to exercise the export paths, so this test is conditionally
     * skipped here — it stays meaningful for downstream environments
     * that omit the SDK.
     */
    public function testConstructorThrowsWhenOpenTelemetrySdkIsAbsent(): void
    {
        if (class_exists('\\OpenTelemetry\\API\\Trace\\SpanContext')) {
            self::markTestSkipped('open-telemetry/sdk is installed in this environment; the guard cannot fire.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenTelemetryExporter requires open-telemetry/sdk');

        new OpenTelemetryExporter(new \stdClass());
    }

    public function testExportEmitsSpanWithDefaultNameWhenOperationAttributeIsAbsent(): void
    {
        $exporterSink = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporterSink));
        $tracer = $tracerProvider->getTracer('test');

        $bridge = new OpenTelemetryExporter($tracer);

        $traceId = new Ulid();
        $bridge->export(new TraceContext($traceId, ['kind' => 'unit-test']));

        $tracerProvider->shutdown();

        $spans = $exporterSink->getSpans();
        self::assertCount(1, $spans, 'one span per export call');
        self::assertSame(OpenTelemetryExporter::DEFAULT_SPAN_NAME, $spans[0]->getName());

        $attributes = $spans[0]->getAttributes()->toArray();
        self::assertSame('unit-test', $attributes[OpenTelemetryExporter::ATTRIBUTE_PREFIX . 'kind']);
        self::assertSame((string) $traceId, $attributes[OpenTelemetryExporter::TRACE_ID_ATTRIBUTE]);
    }

    public function testOperationAttributeBecomesTheSpanNameAndAllScalarsArePrefixed(): void
    {
        $exporterSink = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporterSink));
        $tracer = $tracerProvider->getTracer('test');

        $bridge = new OpenTelemetryExporter($tracer);

        $traceId = new Ulid();
        $bridge->export(new TraceContext($traceId, [
            OpenTelemetryExporter::OPERATION_ATTRIBUTE => 'order.checkout',
            'order_id' => 4711,
            'tier' => 'gold',
            'enabled' => true,
            // Null is the only non-scalar TraceContext allows; the
            // exporter must not stamp it.
            'optional_missing' => null,
        ]));

        $tracerProvider->shutdown();

        $spans = $exporterSink->getSpans();
        self::assertCount(1, $spans);
        self::assertSame('order.checkout', $spans[0]->getName());

        $attributes = $spans[0]->getAttributes()->toArray();
        self::assertSame(4711, $attributes[OpenTelemetryExporter::ATTRIBUTE_PREFIX . 'order_id']);
        self::assertSame('gold', $attributes[OpenTelemetryExporter::ATTRIBUTE_PREFIX . 'tier']);
        self::assertTrue($attributes[OpenTelemetryExporter::ATTRIBUTE_PREFIX . 'enabled']);
        self::assertArrayNotHasKey(OpenTelemetryExporter::ATTRIBUTE_PREFIX . 'optional_missing', $attributes);
        // The operation attribute is reflected onto the span via the prefixed
        // copy as well — both the name and a queryable attribute carry it.
        self::assertSame('order.checkout', $attributes[OpenTelemetryExporter::ATTRIBUTE_PREFIX . OpenTelemetryExporter::OPERATION_ATTRIBUTE]);
        self::assertSame((string) $traceId, $attributes[OpenTelemetryExporter::TRACE_ID_ATTRIBUTE]);
    }

    public function testTracerMissingSpanBuilderMethodIsIgnoredSilently(): void
    {
        $shapeShiftedTracer = new class () {
            // No spanBuilder method on purpose — simulates an SDK whose
            // shape no longer matches what this bridge built against.
        };

        $bridge = new OpenTelemetryExporter($shapeShiftedTracer);

        // No exception, no log calls — early return inside export().
        $bridge->export(new TraceContext(new Ulid(), []));

        $this->expectNotToPerformAssertions();
    }

    public function testExportLogsWarningWhenSdkSurfaceHasDrifted(): void
    {
        $broken = new class () {
            public function spanBuilder(string $name): object
            {
                throw new \RuntimeException('simulated SDK drift: ' . $name);
            }
        };

        $recorder = new class () extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $bridge = new OpenTelemetryExporter($broken, $recorder);
        $bridge->export(new TraceContext(new Ulid(), []));

        self::assertCount(1, $recorder->records);
        self::assertSame('warning', $recorder->records[0]['level']);
        self::assertStringContainsString('OpenTelemetry export failed', $recorder->records[0]['message']);
        self::assertInstanceOf(\RuntimeException::class, $recorder->records[0]['context']['exception']);
    }
}
