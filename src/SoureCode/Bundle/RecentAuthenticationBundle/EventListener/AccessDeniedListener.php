<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\EventListener;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedListener
{
    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $loginRoute,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        if (!in_array(RecentAuthenticationVoter::IS_AUTHENTICATED_RECENTLY, $exception->getAttributes(), true)) {
            return;
        }

        $request = $event->getRequest();
        $this->recentAuthentication->setReturnPath($request->getRequestUri());

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate($this->loginRoute),
        ));
    }
}
