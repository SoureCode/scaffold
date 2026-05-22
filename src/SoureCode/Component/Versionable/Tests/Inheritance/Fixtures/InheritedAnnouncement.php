<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Inheritance\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
class InheritedAnnouncement extends InheritedDocument
{
    #[Versioned]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $audience = null;

    public function getAudience(): ?string
    {
        return $this->audience;
    }

    public function setAudience(?string $audience): void
    {
        $this->audience = $audience;
    }
}
