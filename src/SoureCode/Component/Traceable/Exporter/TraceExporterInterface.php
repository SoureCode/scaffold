<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Exporter;

use SoureCode\Component\Traceable\TraceContextInterface;

/**
 * Hands a finished trace context off to an external observability backend.
 *
 * Implementations decide the wire format (W3C, Jaeger, Zipkin, OTLP, …) and
 * the transport (HTTP, gRPC, log line). The bundle ships an
 * OpenTelemetry-bridge implementation guarded by class_exists so it does
 * not pull a hard dependency on `open-telemetry/sdk`.
 */
interface TraceExporterInterface
{
    public function export(TraceContextInterface $context): void;
}
