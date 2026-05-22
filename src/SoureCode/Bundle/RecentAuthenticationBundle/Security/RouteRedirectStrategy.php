<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RouteRedirectStrategy implements RedirectStrategyInterface
{
    /**
     * @param string $returnPathParameter Query parameter under which the original
     *                                    `$returnPath` is forwarded to the login
     *                                    route. The login route can pick it up
     *                                    (e.g. as `_target_path` for Symfony's
     *                                    default form login) and redirect the
     *                                    user back after re-auth.
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $loginRoute,
        private readonly string $returnPathParameter = '_target_path',
    ) {
    }

    public function redirectForReauth(Request $request, ?string $returnPath): Response
    {
        $parameters = [];

        if ($returnPath !== null) {
            $parameters[$this->returnPathParameter] = $returnPath;
        }

        return new RedirectResponse(
            $this->urlGenerator->generate($this->loginRoute, $parameters),
        );
    }
}
