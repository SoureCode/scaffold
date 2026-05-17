<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department')]
class Department
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }
}
