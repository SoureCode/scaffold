<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\AuthorableBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\DeletedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\DeletedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'composition_article')]
class FullyDecoratedArticle
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;
    use CreatedByTrait;
    use UpdatedByTrait;
    use DeletedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[Versioned]
    public string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
