<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Removable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;

#[ORM\Entity]
#[ORM\Table(name: 'removable_article')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[DeletedAt]
    private ?\DateTimeImmutable $deletedAt = null;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }
}
