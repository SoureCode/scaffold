<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

final class TimestampableMetadata
{
    /**
     * @param list<CreatedAtBinding> $createdBindings
     * @param list<UpdatedAtBinding> $updatedBindings
     * @param list<ChangedAtBinding> $changedBindings
     */
    public function __construct(
        public readonly array $createdBindings,
        public readonly array $updatedBindings,
        public readonly array $changedBindings,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->createdBindings === []
            && $this->updatedBindings === []
            && $this->changedBindings === [];
    }
}
