<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_account')]
#[Versioned]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\OneToOne(mappedBy: 'account', targetEntity: AccountSettings::class, cascade: ['persist'])]
    private ?AccountSettings $settings = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSettings(): ?AccountSettings
    {
        return $this->settings;
    }

    public function setSettings(?AccountSettings $settings): void
    {
        $this->settings = $settings;
    }
}
