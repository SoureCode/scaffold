<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\UlidIdentifier\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'ulid_entity')]
#[Versioned]
class UlidEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    public function __construct(string $title, ?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
        $this->title = $title;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
