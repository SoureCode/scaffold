<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Event;

final class SettingRemovedEvent
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $previousValue,
    ) {
    }
}
