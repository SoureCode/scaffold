<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Tests\Exporter;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Traceable\Exporter\OpenTelemetryExporter;

final class OpenTelemetryExporterTest extends TestCase
{
    public function testConstructorThrowsWhenOpenTelemetrySdkIsAbsent(): void
    {
        if (class_exists('\\OpenTelemetry\\API\\Trace\\SpanContext')) {
            self::markTestSkipped('open-telemetry/sdk is installed; the guard cannot be exercised in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenTelemetryExporter requires open-telemetry/sdk');

        new OpenTelemetryExporter(new \stdClass());
    }
}
