<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests\Support;

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;

final class NullAuthorProvider implements AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object
    {
        return null;
    }
}
