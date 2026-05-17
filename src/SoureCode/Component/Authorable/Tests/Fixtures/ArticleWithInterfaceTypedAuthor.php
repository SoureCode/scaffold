<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Authorable\Attribute\CreatedBy;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_article_interface_typed')]
class ArticleWithInterfaceTypedAuthor
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[CreatedBy]
    private ?AuthorContract $createdBy = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
