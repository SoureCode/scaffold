<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'channel')]
class Channel
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Hub::class, inversedBy: 'channels')]
    #[ORM\JoinColumn(nullable: false)]
    private Hub $hub;

    public function __construct(string $title, Hub $hub)
    {
        $this->title = $title;
        $this->hub = $hub;
        $hub->addChannel($this);
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
