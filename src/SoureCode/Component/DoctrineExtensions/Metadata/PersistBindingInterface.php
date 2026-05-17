<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

interface PersistBindingInterface
{
    public function getProperty(): \ReflectionProperty;
}
