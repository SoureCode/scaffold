<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures\Evolution\AddScalar\After;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'evolution_article')]
#[Versioned]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $title = '';

    #[ORM\Column(type: Types::STRING)]
    public string $body = '';
}
