<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures\Evolution\AddSingle\Before;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evolution_category')]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $name = '';
}
