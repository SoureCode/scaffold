<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Lock\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'lock_probe_entity')]
#[Versioned]
class Lockable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $lockVersion = 1;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

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

    public function getLockVersion(): int
    {
        return $this->lockVersion;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
