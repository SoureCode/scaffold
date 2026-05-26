<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\VersionField\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Geo
{
    #[ORM\Column(type: Types::FLOAT)]
    private float $latitude;

    #[ORM\Column(type: Types::FLOAT)]
    private float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }
}
