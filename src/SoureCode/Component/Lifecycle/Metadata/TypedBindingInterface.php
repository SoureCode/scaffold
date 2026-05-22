<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

/**
 * Implemented by every Timestampable binding so the mapping listener can
 * read the Doctrine column type without dispatching on the concrete
 * binding class.
 */
interface TypedBindingInterface
{
    public function getType(): string;
}
