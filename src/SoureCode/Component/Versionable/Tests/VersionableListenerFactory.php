<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\Internal\SnapshotTargetResolver;
use SoureCode\Component\Versionable\Internal\SnapshotWriter;
use SoureCode\Component\Versionable\Internal\VersionIncrementer;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Wires the listener and its collaborators the way the bundle does, for the
 * plain-Doctrine integration tests that have no service container.
 */
final class VersionableListenerFactory
{
    public static function create(
        VersionableMetadataFactory $metadataFactory,
        ClockInterface $clock,
        ?LoggerInterface $logger = null,
    ): VersionableListener {
        $logger ??= new NullLogger();

        return new VersionableListener(
            new SnapshotTargetResolver($metadataFactory),
            new VersionIncrementer($metadataFactory),
            new SnapshotWriter($metadataFactory, $clock, $logger),
        );
    }
}
