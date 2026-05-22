<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Tests\Support;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RedirectStrategyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect strategy that always issues a 302 to a fixed URL so tests can
 * exercise AccessDeniedListener without standing up a router.
 */
final class StaticRedirectStrategy implements RedirectStrategyInterface
{
    public function __construct(
        private readonly string $target = '/login',
    ) {
    }

    public function redirectForReauth(Request $request, ?string $returnPath): Response
    {
        return new RedirectResponse($this->target);
    }
}
