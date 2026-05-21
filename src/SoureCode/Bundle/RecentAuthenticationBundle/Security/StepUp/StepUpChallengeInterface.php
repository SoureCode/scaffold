<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security\StepUp;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extension point for tier-2 re-auth challenges (WebAuthn, TOTP, magic-link,
 * SSO step-up, …).
 *
 * Consumers implement this and tag the service `sourecode.recent_auth.step_up`
 * to make it discoverable. The default {@see \SoureCode\Bundle\RecentAuthenticationBundle\Controller\ReauthController}
 * does not consult step-up challenges; the integration is intentionally left
 * to the host application because the UX varies per challenge.
 */
interface StepUpChallengeInterface
{
    public function supports(Request $request): bool;

    public function challenge(Request $request): Response;

    public function isVerified(Request $request): bool;
}
