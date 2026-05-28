<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RelationBump\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'relbump_loud')]
#[Versioned]
class LoudItem
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Partner::class, inversedBy: 'loudItems')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Partner $partner = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setPartner(?Partner $partner): void
    {
        $this->partner = $partner;
    }
}
