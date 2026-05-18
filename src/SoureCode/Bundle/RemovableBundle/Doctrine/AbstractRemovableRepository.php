<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Removable\Repository\RemovableRepositoryTrait;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractRemovableRepository extends ServiceEntityRepository
{
    use RemovableRepositoryTrait;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
        ClockInterface $clock,
        TimestampableMetadataFactory $timestampableMetadata,
        AuthorableMetadataFactory $authorableMetadata,
        ?AuthorProviderInterface $authorProvider = null,
    ) {
        parent::__construct($registry, $entityClass);
        $this->clock = $clock;
        $this->timestampableMetadata = $timestampableMetadata;
        $this->authorableMetadata = $authorableMetadata;
        $this->authorProvider = $authorProvider;
    }
}
