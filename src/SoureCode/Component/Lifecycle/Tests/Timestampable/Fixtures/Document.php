<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\ChangedAt;

#[ORM\Entity]
#[ORM\Table(name: 'document')]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Person $owner;

    #[ChangedAt(field: 'owner.department.code')]
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deptCodeChangedAt = null;

    public function __construct(string $title, Person $owner)
    {
        $this->title = $title;
        $this->owner = $owner;
    }

    public function getDeptCodeChangedAt(): ?\DateTimeImmutable
    {
        return $this->deptCodeChangedAt;
    }
}
