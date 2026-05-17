<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $body;

    #[ORM\ManyToOne(targetEntity: RichArticle::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private RichArticle $article;

    public function __construct(string $body, RichArticle $article)
    {
        $this->body = $body;
        $this->article = $article;
        $article->addComment($this);
    }

    public function getId(): int
    {
        return $this->id;
    }
}
