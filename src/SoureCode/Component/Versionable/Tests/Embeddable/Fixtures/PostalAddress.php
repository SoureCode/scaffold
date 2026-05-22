<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Embeddable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class PostalAddress
{
    #[ORM\Column(type: Types::STRING)]
    private string $street;

    #[ORM\Column(type: Types::STRING)]
    private string $city;

    public function __construct(string $street, string $city)
    {
        $this->street = $street;
        $this->city = $city;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}
