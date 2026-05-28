<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RelationBump\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'relbump_partner')]
#[Versioned]
class Partner
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    /**
     * @var Collection<int, LoudItem>
     */
    #[ORM\OneToMany(targetEntity: LoudItem::class, mappedBy: 'partner')]
    private Collection $loudItems;

    /**
     * @var Collection<int, QuietItem>
     */
    #[ORM\OneToMany(targetEntity: QuietItem::class, mappedBy: 'partner')]
    private Collection $quietItems;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->loudItems = new ArrayCollection();
        $this->quietItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
