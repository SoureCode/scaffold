<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_article')]
#[Versioned]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $internalNote = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    public function setInternalNote(?string $note): void
    {
        $this->internalNote = $note;
    }
}
