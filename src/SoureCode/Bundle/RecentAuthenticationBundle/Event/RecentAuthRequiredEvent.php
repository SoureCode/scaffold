<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Event;

use Symfony\Component\HttpFoundation\Request;

/**
 * Dispatched when the AccessDeniedListener is about to redirect to the
 * re-auth login because IS_AUTHENTICATED_RECENTLY was denied. Subscribers
 * can audit, rate-limit, or short-circuit by setting a response on the
 * event (handled inside AccessDeniedListener).
 */
final class RecentAuthRequiredEvent
{
    public function __construct(
        public readonly Request $request,
        public readonly ?string $returnPath,
    ) {
    }
}
