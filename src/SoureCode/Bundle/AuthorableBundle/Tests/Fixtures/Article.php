<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\AuthorableBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\UpdatedByTrait;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_bundle_article')]
class Article
{
    use CreatedByTrait;
    use UpdatedByTrait;

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
