<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Versioned
{
    public function __construct(
        /**
         * Per-class override for relationship-bump propagation. `null` means
         * "no opinion — use the global default" (configured on the bundle
         * extension via `bump_relations`, or `true` if the host hasn't set
         * one). `true` / `false` is an explicit per-class decision that
         * wins over the global default. The runtime one-shot
         * {@see \SoureCode\Component\Versionable\Versioner::bumpRelations()}
         * still wins over both.
         */
        public readonly ?bool $bumpRelations = null,
    ) {
    }
}
