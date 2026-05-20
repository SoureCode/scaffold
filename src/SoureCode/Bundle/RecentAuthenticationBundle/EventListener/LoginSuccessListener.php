<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\EventListener;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSuccessListener
{
    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $return = $this->recentAuthentication->takeReturnPath();

        if ($return === null) {
            return;
        }

        $this->recentAuthentication->mark();
        $event->setResponse(new RedirectResponse($return));
    }
}
