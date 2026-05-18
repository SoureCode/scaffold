<?php

declare(strict_types=1);

namespace SoureCode\Component\Removable\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Clock\ClockInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Removable\Repository\RemovableRepositoryTrait;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

/**
 * @extends EntityRepository<ArticleWithoutMarker>
 */
final class ArticleWithoutMarkerRepository extends EntityRepository
{
    use RemovableRepositoryTrait;

    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadata $classMetadata,
        ClockInterface $clock,
        TimestampableMetadataFactory $timestampableMetadata,
        AuthorableMetadataFactory $authorableMetadata,
    ) {
        parent::__construct($entityManager, $classMetadata);
        $this->clock = $clock;
        $this->timestampableMetadata = $timestampableMetadata;
        $this->authorableMetadata = $authorableMetadata;
    }
}
