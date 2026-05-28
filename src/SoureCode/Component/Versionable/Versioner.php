<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassGenerator;
use SoureCode\Component\Versionable\Internal\History\HistoryClassNamer;
use SoureCode\Component\Versionable\Internal\History\HistoryHydrator;
use SoureCode\Component\Versionable\Internal\RelationBumpState;
use SoureCode\Component\Versionable\Internal\VersionRowApplier;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

/**
 * Façade over the version store. Internally delegates to {@see VersionReader}
 * (history queries + diff), {@see VersionApplier} (apply-onto-live-entity),
 * and {@see VersionPruner} (history purge). Host applications that only
 * need one half of the API may inject the sub-services directly.
 */
final class Versioner implements VersionerInterface
{
    private readonly VersionReader $reader;
    private readonly VersionApplier $applier;
    private readonly VersionPruner $pruner;

    public function __construct(
        EntityManagerInterface $entityManager,
        VersionableMetadataFactory $metadataFactory,
        LoggerInterface $logger = new NullLogger(),
        private readonly RelationBumpState $relationBumpState = new RelationBumpState(),
        ?HistoryHydrator $historyHydrator = null,
    ) {
        $rowApplier = new VersionRowApplier($entityManager, $metadataFactory, $logger);
        $historyHydrator ??= new HistoryHydrator(
            $entityManager,
            $metadataFactory,
            new HistoryClassFactory(
                new HistoryClassGenerator($metadataFactory, $entityManager),
                sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'sourecode-versionable',
            ),
        );

        $this->reader = new VersionReader($entityManager, $metadataFactory, $historyHydrator);
        $this->applier = new VersionApplier($entityManager, $metadataFactory, $rowApplier, $this->reader);
        $this->pruner = new VersionPruner($entityManager, $metadataFactory);
    }

    /**
     * Resolve the runtime-generated `*History` FQCN for a versioned entity.
     * Useful in tests and for type assertions on values returned by
     * {@see findByVersion()}, {@see findHistory()}, {@see findLatestVersion()}.
     */
    public static function historyClassFor(string $originalClass): string
    {
        return HistoryClassNamer::historyClassFor($originalClass);
    }

    public function bumpRelations(bool $value): void
    {
        $this->relationBumpState->setOverride($value);
    }

    public function findHistory(string $className, mixed $entityId): array
    {
        return $this->reader->findHistory($className, $entityId);
    }

    public function findByVersion(string $className, mixed $entityId, int $version): ?object
    {
        return $this->reader->findByVersion($className, $entityId, $version);
    }

    public function findLatestVersion(string $className, mixed $entityId): ?object
    {
        return $this->reader->findLatestVersion($className, $entityId);
    }

    public function applyVersion(
        object $entity,
        int $version,
        array $onlyFields = [],
        bool $cascade = false,
        ?bool $bumpRelations = null,
    ): AppliedVersion {
        if ($bumpRelations !== null) {
            $this->relationBumpState->setOverride($bumpRelations);
        }

        return $this->applier->applyVersion($entity, $version, $onlyFields, $cascade);
    }

    public function diff(string $className, mixed $entityId, int $fromVersion, int $toVersion): ?VersionDiff
    {
        return $this->reader->diff($className, $entityId, $fromVersion, $toVersion);
    }

    public function prune(string $className, \DateTimeInterface $olderThan, int $keepLast = 1): int
    {
        return $this->pruner->prune($className, $olderThan, $keepLast);
    }
}
