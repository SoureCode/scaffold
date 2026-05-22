<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\ORM\Mapping\ClassMetadata;
use SoureCode\Component\DoctrineExtensions\EventListener\AbstractMetadataMappingListener;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataInterface;
use SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface;

/**
 * Test double exposing each `mapIfMissing` invocation as an entry on
 * `$calls` so the abstract base class can be unit-tested without booting
 * Doctrine. Each call records the binding instance + nullable flag the
 * base supplied.
 *
 * `getDeletedBindings` reads from {@see FakeBehaviorMetadata::getDeletedBindings}
 * — the same shape the concrete Authorable / Timestampable bundles use
 * via their metadata classes.
 */
final class RecordingMappingListener extends AbstractMetadataMappingListener
{
    /**
     * @var list<array{binding: PersistBindingInterface, nullable: bool}>
     */
    public array $calls = [];

    protected function mapIfMissing(
        ClassMetadata $classMetadata,
        PersistBindingInterface $binding,
        bool $nullable,
    ): void {
        $this->calls[] = ['binding' => $binding, 'nullable' => $nullable];
    }

    protected function getDeletedBindings(BehaviorMetadataInterface $metadata): iterable
    {
        \assert($metadata instanceof FakeBehaviorMetadata);

        return $metadata->getDeletedBindings();
    }
}
