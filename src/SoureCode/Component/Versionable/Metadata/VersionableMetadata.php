<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Metadata;

final class VersionableMetadata
{
    /**
     * @param list<VersionedBinding> $bindings
     */
    public function __construct(
        public readonly array $bindings,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->bindings === [];
    }
}
