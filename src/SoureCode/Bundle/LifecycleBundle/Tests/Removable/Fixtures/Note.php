<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Removable\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\LifecycleBundle\Doctrine\DeletedAtTrait;

#[ORM\Entity]
#[ORM\Table(name: 'removable_bundle_note')]
class Note
{
    use DeletedAtTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    public string $body;

    public function __construct(string $body)
    {
        $this->body = $body;
    }
}
