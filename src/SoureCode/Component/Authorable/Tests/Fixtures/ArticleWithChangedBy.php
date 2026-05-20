<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Authorable\Attribute\ChangedBy;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_article_changed_by')]
class ArticleWithChangedBy
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::STRING)]
    private string $body;

    #[ChangedBy(field: ['title', 'body'])]
    private ?User $changedBy = null;

    public function __construct(string $title, string $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }
}
