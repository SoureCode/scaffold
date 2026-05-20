<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Timestampable\Attribute\CreatedAt;

#[ORM\Entity]
#[ORM\Table(name: 'timestampable_article_predeclared_column')]
class ArticleWithPredeclaredColumn
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    // CreatedAt would default the listener to (type: DATETIMETZ_IMMUTABLE, nullable: false).
    // The pre-declared column below MUST win: nullable=true and a different physical name.
    #[CreatedAt]
    #[ORM\Column(name: 'created_at_custom', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
