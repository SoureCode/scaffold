<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Self-referential entity used to exercise the related-watcher / collection-watcher
 * paths of {@see \SoureCode\Component\DoctrineExtensions\EventListener\AbstractFlushListener}:
 *   - dotted path `related.label`
 *   - collection-name path `children`
 *   - collection-element path `children.label`
 *   - cycles (a.related = b, b.related = a)
 *   - many-to-many (`tags`) so collection deletions fire on the owning side.
 */
#[ORM\Entity]
#[ORM\Table(name: 'docext_related_stampable')]
class RelatedStampable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $label;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?self $related = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'related')]
    public Collection $children;

    /**
     * Owning-side many-to-many. Clearing this collection schedules a
     * collection deletion (`scheduledCollectionDeletions`); inverse-side
     * collections like `$children` only contribute to
     * `scheduledCollectionUpdates`.
     *
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'docext_related_stampable_tags')]
    public Collection $tags;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $watcherStamp = null;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->children = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }
}
