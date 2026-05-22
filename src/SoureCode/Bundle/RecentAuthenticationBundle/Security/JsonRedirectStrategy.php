<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Variant of {@see RedirectStrategyInterface} for JSON / API clients that
 * cannot follow an HTTP redirect: returns a 401 with a JSON body
 * describing the re-authentication requirement and the URL the client
 * should redirect the user to once authenticated. Wire this in place of
 * `RouteRedirectStrategy` for an API firewall.
 */
final class JsonRedirectStrategy implements RedirectStrategyInterface
{
    public function __construct(
        private readonly string $message = 'Fresh authentication required.',
    ) {
    }

    public function redirectForReauth(Request $request, ?string $returnPath): Response
    {
        return new JsonResponse(
            [
                'error' => 'recent_authentication_required',
                'message' => $this->message,
                'return_path' => $returnPath,
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
