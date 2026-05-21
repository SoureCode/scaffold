<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\EventListener;

use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthRequiredEvent;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RedirectStrategyInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedListener
{
    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
        private readonly RedirectStrategyInterface $redirectStrategy,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
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
        $returnPath = null;

        if ($request->isMethodSafe()) {
            $returnPath = $request->getRequestUri();
            $this->recentAuthentication->setReturnPath($returnPath);
        }

        $this->eventDispatcher?->dispatch(new RecentAuthRequiredEvent($request, $returnPath));

        $event->setResponse($this->redirectStrategy->redirectForReauth($request, $returnPath));
    }
}
