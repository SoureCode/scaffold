<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Event;

final class FeatureFlagRemovedEvent
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
