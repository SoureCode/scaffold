<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests\Support;

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;

final class FixedAuthorProvider implements AuthorProviderInterface
{
    private ?object $author = null;

    public function setAuthor(?object $author): void
    {
        $this->author = $author;
    }

    public function getCurrentAuthor(): ?object
    {
        return $this->author;
    }
}
