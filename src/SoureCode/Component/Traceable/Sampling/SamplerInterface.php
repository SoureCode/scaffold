<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Sampling;

interface SamplerInterface
{
    /**
     * Decides whether the trace context being created should be marked as
     * sampled. Listeners use the verdict to set the W3C sampled flag and
     * may decide not to publish the span if sampled is false.
     *
     * @param array<string, mixed> $context arbitrary hints — request path,
     *                                      command name, message class, …
     */
    public function shouldSample(array $context = []): bool;
}
