<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\ImpersonatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\UpdatedByTrait;

#[ORM\Entity]
#[ORM\Table(name: 'authorable_bundle_audited_article')]
class AuditedArticle
{
    use CreatedByTrait;
    use UpdatedByTrait;
    use ImpersonatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
