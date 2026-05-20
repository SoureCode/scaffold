<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'docext_interface_stampable')]
class InterfaceStampable implements StampableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $label;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $interfaceStamp = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function getInterfaceStamp(): ?string
    {
        return $this->interfaceStamp;
    }

    public function setInterfaceStamp(?string $stamp): void
    {
        $this->interfaceStamp = $stamp;
    }
}
