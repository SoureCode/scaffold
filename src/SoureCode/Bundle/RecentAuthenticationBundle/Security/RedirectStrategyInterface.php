<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the redirect response sent when an attribute requiring fresh auth
 * was denied. The default implementation generates a redirect to a named
 * route; a custom strategy can render a re-auth form inline, return a JSON
 * 401, or short-circuit the redirect entirely.
 */
interface RedirectStrategyInterface
{
    public function redirectForReauth(Request $request, ?string $returnPath): Response;
}
