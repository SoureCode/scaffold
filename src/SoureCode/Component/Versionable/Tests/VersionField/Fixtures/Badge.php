<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\VersionField\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'version_probe_badge')]
#[Versioned]
class Badge
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $code;

    #[ORM\OneToOne(targetEntity: Subject::class, mappedBy: 'badge')]
    private ?Subject $subject = null;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }
}
