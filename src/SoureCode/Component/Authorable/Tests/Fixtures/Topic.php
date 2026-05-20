<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_topic')]
class Topic
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $label;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
