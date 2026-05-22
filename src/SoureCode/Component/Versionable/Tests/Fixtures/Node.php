<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_node')]
class Node
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[Versioned]
    #[ORM\Column(type: Types::STRING)]
    private string $label;

    #[Versioned]
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Node $parent = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function setParent(?Node $parent): void
    {
        $this->parent = $parent;
    }
}
