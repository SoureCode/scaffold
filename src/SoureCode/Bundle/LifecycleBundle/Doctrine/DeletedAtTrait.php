<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;

trait DeletedAtTrait
{
    #[DeletedAt]
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    /**
     * Nullable on purpose: passing `null` clears the soft-delete marker
     * (used by `Remover::restore()`). Sibling `Created`/`Updated` traits
     * are non-nullable because those columns are only ever set, never cleared.
     */
    public function setDeletedAt(?\DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}
