<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Author;

interface AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object;
}
