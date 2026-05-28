<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

/**
 * Versioned entity with a `createdBy` association registered by a
 * loadClassMetadata listener — exactly the shape of an entity using
 * Authorable's `CreatedByTrait` against a non-versioned `User`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rtmap_work_item')]
#[Versioned]
class WorkItem
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

    private ?PlainUser $createdBy = null;

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

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedBy(): ?PlainUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?PlainUser $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
