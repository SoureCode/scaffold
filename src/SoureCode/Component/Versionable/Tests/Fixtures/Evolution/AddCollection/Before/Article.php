<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures\Evolution\AddCollection\Before;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'evolution_article')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Versioned]
    #[ORM\Column(type: Types::STRING)]
    public string $title = '';
}
