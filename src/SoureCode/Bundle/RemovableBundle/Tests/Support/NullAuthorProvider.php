<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests\Support;

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;

final class NullAuthorProvider implements AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object
    {
        return null;
    }
}
