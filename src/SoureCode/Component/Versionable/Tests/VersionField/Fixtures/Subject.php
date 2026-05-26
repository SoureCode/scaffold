<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\VersionField\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'version_probe_subject')]
#[Versioned]
class Subject
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $title;

    #[ORM\Column]
    private ProbeStatus $status = ProbeStatus::Draft;

    #[ORM\Embedded(class: Geo::class)]
    private Geo $geo;

    #[ORM\ManyToOne(targetEntity: Owner::class, inversedBy: 'subjects')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Owner $owner = null;

    #[ORM\OneToOne(targetEntity: Badge::class, inversedBy: 'subject')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Badge $badge = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'subjects')]
    private Collection $tags;

    public function __construct(string $title, Geo $geo)
    {
        $this->title = $title;
        $this->geo = $geo;
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setStatus(ProbeStatus $status): void
    {
        $this->status = $status;
    }

    public function setGeo(Geo $geo): void
    {
        $this->geo = $geo;
    }

    public function setOwner(?Owner $owner): void
    {
        $this->owner = $owner;
    }

    public function setBadge(?Badge $badge): void
    {
        $this->badge = $badge;
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
