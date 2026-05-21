<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RouteRedirectStrategy implements RedirectStrategyInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $loginRoute,
    ) {
    }

    public function redirectForReauth(Request $request, ?string $returnPath): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate($this->loginRoute),
        );
    }
}
