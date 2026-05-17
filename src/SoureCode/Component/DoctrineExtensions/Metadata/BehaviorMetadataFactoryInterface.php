<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Metadata;

interface BehaviorMetadataFactoryInterface
{
    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): BehaviorMetadataInterface;
}
