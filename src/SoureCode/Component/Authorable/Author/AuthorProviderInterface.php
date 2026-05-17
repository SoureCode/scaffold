<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Author;

interface AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object;
}
