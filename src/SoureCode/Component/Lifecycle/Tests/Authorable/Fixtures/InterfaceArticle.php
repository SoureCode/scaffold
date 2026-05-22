<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Authorable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\AuthorableInterface;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_interface_article')]
class InterfaceArticle implements AuthorableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $updatedBy = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(object $author): void
    {
        if (!$author instanceof User) {
            throw new \TypeError(\sprintf('Expected %s, got %s', User::class, $author::class));
        }

        $this->createdBy = $author;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(object $author): void
    {
        if (!$author instanceof User) {
            throw new \TypeError(\sprintf('Expected %s, got %s', User::class, $author::class));
        }

        $this->updatedBy = $author;
    }
}
