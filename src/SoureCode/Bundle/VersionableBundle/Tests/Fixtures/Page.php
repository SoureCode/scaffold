<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_bundle_page')]
#[Versioned]
class Page
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
