<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Security;

use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

final class SecurityImpersonatorProvider implements ImpersonatorProviderInterface
{
    public function __construct(
        private readonly ?Security $security = null,
    ) {
    }

    public function getImpersonator(): ?object
    {
        if ($this->security === null) {
            return null;
        }

        $token = $this->security->getToken();

        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        return $token->getOriginalToken()->getUser();
    }
}
