<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Exporter;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
 *
 * SDK version drift: the `method_exists` checks and the surrounding
 * try/catch in {@see export()} mean that an SDK upgrade that renames or
 * removes a method downgrades from "broken application" to "missing
 * span + warning log". If you depend on OTEL being authoritative, pin
 * `open-telemetry/sdk` in your application's composer.json instead of
 * relying on the soft-guard behaviour.
 */
final class OpenTelemetryExporter implements TraceExporterInterface
{
    /**
     * Attribute on `TraceContext::$attributes` used to pick the OTEL span
     * name. Falls back to {@see DEFAULT_SPAN_NAME} when not provided.
     */
    public const string OPERATION_ATTRIBUTE = 'operation';

    /**
     * Prefix applied to every TraceContext attribute when stamped onto the
     * exported OTEL span — keeps the project namespace out of the way of
     * standard OTEL semantic conventions.
     */
    public const string ATTRIBUTE_PREFIX = 'sourecode.';

    public const string DEFAULT_SPAN_NAME = 'sourecode.trace';

    public const string TRACE_ID_ATTRIBUTE = self::ATTRIBUTE_PREFIX . 'trace_id';

    /**
     * @param object $tracer A `\OpenTelemetry\API\Trace\TracerInterface` instance.
     *                       Typed loosely so this file parses without the SDK installed.
     */
    public function __construct(
        private readonly object $tracer,
        private readonly LoggerInterface $logger = new NullLogger(),
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

        try {
            $builder = $this->tracer->spanBuilder((string) ($context->getAttributes()[self::OPERATION_ATTRIBUTE] ?? self::DEFAULT_SPAN_NAME));

            foreach ($context->getAttributes() as $key => $value) {
                if (is_scalar($value) && method_exists($builder, 'setAttribute')) {
                    $builder->setAttribute(self::ATTRIBUTE_PREFIX . $key, $value);
                }
            }

            if (method_exists($builder, 'setAttribute')) {
                $builder->setAttribute(self::TRACE_ID_ATTRIBUTE, (string) $context->getId());
            }

            if (method_exists($builder, 'startSpan')) {
                $span = $builder->startSpan();

                if (method_exists($span, 'end')) {
                    $span->end();
                }
            }
        } catch (\Throwable $exception) {
            // SDK changed shape (rename, removed method, new required arg)
            // after we built against it. Log loud enough that the upgrade
            // is visible, but never break the host request because the
            // tracing path failed.
            $this->logger->warning(
                'OpenTelemetry export failed; trace dropped. SDK shape may have drifted.',
                ['exception' => $exception],
            );
        }
    }
}
