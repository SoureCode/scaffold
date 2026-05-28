<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Internal;

/**
 * One-shot override for relationship propagation. When the override is set,
 * SnapshotTargetResolver uses it for the whole resolve pass instead of each
 * entity's class-level #[Versioned(bumpRelations: ...)] default. The
 * listener resets it after every onFlush so the override never crosses
 * flushes.
 */
final class RelationBumpState
{
    private ?bool $override = null;

    public function setOverride(bool $value): void
    {
        $this->override = $value;
    }

    public function getOverride(): ?bool
    {
        return $this->override;
    }

    public function reset(): void
    {
        $this->override = null;
    }
}
