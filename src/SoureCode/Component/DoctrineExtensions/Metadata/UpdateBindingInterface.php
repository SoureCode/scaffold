<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

interface UpdateBindingInterface extends PersistBindingInterface
{
    public function isNullable(): bool;
}
