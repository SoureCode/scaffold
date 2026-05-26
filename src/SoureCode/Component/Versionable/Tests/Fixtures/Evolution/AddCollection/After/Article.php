<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures\Evolution\AddCollection\After;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}
