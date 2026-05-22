<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_soft_deletable')]
class SoftDeletable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[DeletedBy]
    private ?User $deletedBy = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?User $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }
}
