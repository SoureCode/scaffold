<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support;

use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;

final class NullAuthorProvider implements AuthorProviderInterface
{
    public function getCurrentAuthor(): ?object
    {
        return null;
    }
}
