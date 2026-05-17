<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Tests\Support;

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;

final class FixedAuthorProvider implements AuthorProviderInterface
{
    public function __construct(
        private ?object $author = null,
    ) {
    }

    public function setAuthor(?object $author): void
    {
        $this->author = $author;
    }

    public function getCurrentAuthor(): ?object
    {
        return $this->author;
    }
}
