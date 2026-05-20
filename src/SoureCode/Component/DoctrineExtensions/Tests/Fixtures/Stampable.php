<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'docext_stampable')]
class Stampable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $label;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $persistStamp = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $updateStamp = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }
}
