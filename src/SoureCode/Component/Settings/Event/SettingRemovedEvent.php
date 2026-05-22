<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Event;

/**
 * Dispatched by {@see \SoureCode\Component\Settings\Manager\AuditedSettingsManager}
 * when a setting is removed.
 *
 * `$previousValue` carries the post-decorator-stack value: subscribers
 * persisting or logging it MUST redact values for sensitive keys —
 * see the analogous note on {@see SettingChangedEvent}.
 */
final class SettingRemovedEvent
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $previousValue,
    ) {
    }
}
