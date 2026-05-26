<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Removable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\DeletedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\DeletedAtTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Component\Versionable\Attribute\Version;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'composition_article')]
#[Versioned]
class FullyDecoratedArticle
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;
    use CreatedByTrait;
    use UpdatedByTrait;
    use DeletedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Version]
    #[ORM\Column(type: Types::INTEGER)]
    public int $version = 0;

    #[ORM\Column(type: Types::STRING)]
    public string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
