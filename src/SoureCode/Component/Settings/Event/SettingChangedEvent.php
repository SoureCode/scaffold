<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Event;

/**
 * Dispatched by {@see \SoureCode\Component\Settings\Manager\AuditedSettingsManager}
 * when a setting is written.
 *
 * Carries the post-decorator-stack value: if a host stacks
 * `Audited → Encrypting → Doctrine`, the event sees the PLAINTEXT (because
 * the Audited layer asked the Encrypting layer to decode for its read).
 * Subscribers that persist or log this event MUST redact values for keys
 * known to be sensitive — the event itself does not know which keys are.
 */
final class SettingChangedEvent
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $previousValue,
        public readonly mixed $newValue,
    ) {
    }
}
