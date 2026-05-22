<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Lifecycle\Attribute\ChangedAt;

#[ORM\Entity]
#[ORM\Table(name: 'hub')]
class Hub
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    /**
     * @var Collection<int, Channel>
     */
    #[ORM\OneToMany(targetEntity: Channel::class, mappedBy: 'hub')]
    private Collection $channels;

    #[ChangedAt(field: 'channels.title')]
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastChannelTitleChangedAt = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->channels = new ArrayCollection();
    }

    public function addChannel(Channel $channel): void
    {
        if (!$this->channels->contains($channel)) {
            $this->channels->add($channel);
        }
    }

    public function getLastChannelTitleChangedAt(): ?\DateTimeImmutable
    {
        return $this->lastChannelTitleChangedAt;
    }
}
