<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Inheritance\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_inherited_document')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: Types::STRING)]
#[ORM\DiscriminatorMap(['memo' => InheritedMemo::class, 'announcement' => InheritedAnnouncement::class])]
#[Versioned]
abstract class InheritedDocument
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

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

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
