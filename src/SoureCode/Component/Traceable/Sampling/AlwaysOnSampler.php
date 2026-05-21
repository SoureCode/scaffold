<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Sampling;

final class AlwaysOnSampler implements SamplerInterface
{
    public function shouldSample(array $context = []): bool
    {
        return true;
    }
}
