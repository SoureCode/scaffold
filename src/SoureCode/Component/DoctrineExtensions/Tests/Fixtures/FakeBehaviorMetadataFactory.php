<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;

final class FakeBehaviorMetadataFactory implements BehaviorMetadataFactoryInterface
{
    /**
     * @param array<class-string, BehaviorMetadataInterface> $byClass
     */
    public function __construct(
        private readonly array $byClass = [],
    ) {
    }

    public function getMetadataFor(string $class): BehaviorMetadataInterface
    {
        return $this->byClass[$class] ?? new FakeBehaviorMetadata();
    }
}
