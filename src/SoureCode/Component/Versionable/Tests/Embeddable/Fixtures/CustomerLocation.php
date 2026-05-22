<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Embeddable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_customer_location')]
class CustomerLocation
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[Versioned]
    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[Versioned]
    #[ORM\Embedded(class: PostalAddress::class)]
    private PostalAddress $address;

    public function __construct(string $name, PostalAddress $address)
    {
        $this->name = $name;
        $this->address = $address;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAddress(): PostalAddress
    {
        return $this->address;
    }

    public function setAddress(PostalAddress $address): void
    {
        $this->address = $address;
    }
}
