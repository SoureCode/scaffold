<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Inheritance\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InheritedMemo extends InheritedDocument
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $authorNote = null;

    public function getAuthorNote(): ?string
    {
        return $this->authorNote;
    }

    public function setAuthorNote(?string $authorNote): void
    {
        $this->authorNote = $authorNote;
    }
}
