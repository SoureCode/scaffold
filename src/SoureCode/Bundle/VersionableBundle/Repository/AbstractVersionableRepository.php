<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SoureCode\Component\Versionable\Repository\VersionableRepositoryTrait;

/**
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractVersionableRepository extends ServiceEntityRepository
{
    use VersionableRepositoryTrait;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }
}
