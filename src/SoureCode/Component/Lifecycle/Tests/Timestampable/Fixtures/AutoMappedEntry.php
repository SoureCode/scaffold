<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\CreatedAt;
use SoureCode\Component\Lifecycle\Attribute\UpdatedAt;

#[ORM\Entity]
#[ORM\Table(name: 'auto_mapped_entry')]
class AutoMappedEntry
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[CreatedAt]
    private ?\DateTimeInterface $createdAt = null;

    #[UpdatedAt(nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
