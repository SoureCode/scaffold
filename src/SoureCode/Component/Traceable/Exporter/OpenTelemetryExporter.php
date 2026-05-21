<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Exporter;

use SoureCode\Component\Traceable\TraceContextInterface;

/**
 * Bridges a {@see TraceContextInterface} to the official `open-telemetry/sdk`
 * tracer when it is installed. Throws a runtime exception with an actionable
 * message when the SDK is missing — the bundle never builds this service by
 * default, so the error surfaces only when consumers opt in.
 *
 * Why a class_exists guard instead of a hard `composer require`: the toolkit
 * tries to remain framework-agnostic at the component layer; OTEL is one
 * possible export target among many, and most consumers will not have it.
 */
final class OpenTelemetryExporter implements TraceExporterInterface
{
    /**
     * @param object $tracer A `\OpenTelemetry\API\Trace\TracerInterface` instance.
     *                       Typed loosely so this file parses without the SDK installed.
     */
    public function __construct(
        private readonly object $tracer,
    ) {
        if (!class_exists('\\OpenTelemetry\\API\\Trace\\SpanContext')) {
            throw new \RuntimeException(
                'OpenTelemetryExporter requires open-telemetry/sdk. Run `composer require open-telemetry/sdk`.',
            );
        }
    }

    public function export(TraceContextInterface $context): void
    {
        if (!method_exists($this->tracer, 'spanBuilder')) {
            return;
        }

        $builder = $this->tracer->spanBuilder((string) ($context->getAttributes()['operation'] ?? 'sourecode.trace'));

        foreach ($context->getAttributes() as $key => $value) {
            if (is_scalar($value) && method_exists($builder, 'setAttribute')) {
                $builder->setAttribute('sourecode.' . $key, $value);
            }
        }

        if (method_exists($builder, 'setAttribute')) {
            $builder->setAttribute('sourecode.trace_id', (string) $context->getId());
        }

        if (method_exists($builder, 'startSpan')) {
            $span = $builder->startSpan();

            if (method_exists($span, 'end')) {
                $span->end();
            }
        }
    }
}
