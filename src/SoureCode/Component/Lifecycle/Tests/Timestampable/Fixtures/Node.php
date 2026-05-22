<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\ChangedAt;

#[ORM\Entity]
#[ORM\Table(name: 'node')]
class Node
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $label;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Node $parent = null;

    #[ChangedAt(field: 'parent.parent.parent.label')]
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $ancestorLabelChangedAt = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function setParent(?Node $parent): void
    {
        $this->parent = $parent;
    }

    public function getAncestorLabelChangedAt(): ?\DateTimeImmutable
    {
        return $this->ancestorLabelChangedAt;
    }
}
