<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Security;

use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityAuthorProvider implements AuthorProviderInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getCurrentAuthor(): ?object
    {
        return $this->security->getUser();
    }
}
