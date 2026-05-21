<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Event;

final class FeatureFlagToggledEvent
{
    public function __construct(
        public readonly string $name,
        public readonly bool $enabled,
        public readonly bool $created,
    ) {
    }
}
