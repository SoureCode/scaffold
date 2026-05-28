<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

/**
 * Holds two related knobs read by {@see SnapshotTargetResolver} when deciding
 * whether a change should propagate to its relation partner:
 *
 *  1. A **global default** for hosts that want to flip the baseline globally
 *     (e.g. "by default nothing ripples; explicitly opt classes in"). Stays
 *     in place across flushes — the bundle wires it from the
 *     `bump_relations` configuration option, or it defaults to `true`.
 *  2. A **one-shot override** for the next flush, set via
 *     `Versioner::bumpRelations(bool)` or
 *     `Versioner::applyVersion(..., bumpRelations: false)`. Reset by the
 *     listener after every onFlush so it never crosses flushes.
 *
 * Precedence read by the resolver, top wins:
 *
 *  - the one-shot override, when set,
 *  - the entity's `#[Versioned(bumpRelations: …)]`, when not `null`,
 *  - the global default.
 */
final class RelationBumpState
{
    private ?bool $override = null;
    private bool $globalDefault = true;

    public function setOverride(bool $value): void
    {
        $this->override = $value;
    }

    public function getOverride(): ?bool
    {
        return $this->override;
    }

    public function setGlobalDefault(bool $value): void
    {
        $this->globalDefault = $value;
    }

    public function getGlobalDefault(): bool
    {
        return $this->globalDefault;
    }

    public function reset(): void
    {
        $this->override = null;
    }
}
