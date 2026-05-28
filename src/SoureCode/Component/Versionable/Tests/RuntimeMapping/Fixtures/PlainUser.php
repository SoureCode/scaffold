<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\RuntimeMapping\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Non-versioned target — mirrors the typical `App\Entity\User` that
 * Authorable's #[CreatedBy] points at.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rtmap_plain_user')]
class PlainUser
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
