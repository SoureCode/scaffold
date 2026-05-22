<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'person')]
class Person
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Department $department;

    public function __construct(string $name, Department $department)
    {
        $this->name = $name;
        $this->department = $department;
    }
}
