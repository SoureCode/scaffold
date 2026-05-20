<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\TimestampableBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\DeletedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;

#[ORM\Entity]
#[ORM\Table(name: 'timestampable_bundle_note')]
class Note
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
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
