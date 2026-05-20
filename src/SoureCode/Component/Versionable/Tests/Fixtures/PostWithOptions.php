<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Component\Versionable\Attribute\Versioned;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_post_with_options')]
class PostWithOptions
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Versioned]
    #[ORM\Column(type: Types::STRING, length: 64)]
    public string $title = '';

    #[Versioned]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    public string $amount = '0';

    #[Versioned]
    #[ORM\Column(type: Types::STRING, enumType: PostStatus::class)]
    public PostStatus $status = PostStatus::Draft;
}
