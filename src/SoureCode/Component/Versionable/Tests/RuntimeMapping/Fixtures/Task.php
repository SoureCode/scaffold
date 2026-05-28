<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

/**
 * Mimics an entity that uses a trait (e.g. CreatedByTrait) where the FK
 * association is NOT declared via #[ORM\ManyToOne] on the property — a
 * Doctrine listener (here: TaskRuntimeMappingListener) registers it
 * programmatically during loadClassMetadata.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rtmap_task')]
#[Versioned]
class Task
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

    // No #[ORM\ManyToOne] here — the listener supplies the mapping.
    private ?Partner $createdBy = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedBy(): ?Partner
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Partner $partner): void
    {
        $this->createdBy = $partner;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
