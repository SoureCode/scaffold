<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_rich_article')]
#[Versioned]
class RichArticle
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\OneToOne(targetEntity: Profile::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Profile $profile = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'article', cascade: ['persist'])]
    private Collection $comments;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    public function __construct(string $title)
    {
        $this->title = $title;
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function setProfile(?Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }
}
