<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Security;

use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityAuthorProvider implements AuthorProviderInterface
{
    public function __construct(
        private readonly ?Security $security = null,
    ) {
    }

    public function getCurrentAuthor(): ?object
    {
        return $this->security?->getUser();
    }
}
