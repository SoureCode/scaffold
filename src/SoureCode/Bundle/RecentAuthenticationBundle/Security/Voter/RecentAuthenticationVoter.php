<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;

/**
 * @extends Voter<string, mixed>
 */
final class RecentAuthenticationVoter extends Voter
{
    public const string IS_AUTHENTICATED_RECENTLY = 'IS_AUTHENTICATED_RECENTLY';

    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
        private readonly ?AuthenticationTrustResolverInterface $trustResolver = null,
        private readonly bool $requireFullAuthentication = true,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::IS_AUTHENTICATED_RECENTLY;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (
            $this->requireFullAuthentication
            && $this->trustResolver !== null
            && !$this->trustResolver->isFullFledged($token)
        ) {
            return false;
        }

        $ttl = self::resolveTtl($subject);

        return $this->recentAuthentication->isActive($ttl);
    }

    /**
     * Allows tightening the freshness window per resource:
     *
     *   $this->denyAccessUnlessGranted('IS_AUTHENTICATED_RECENTLY', 60);
     *
     * A positive int is a TTL in seconds; null falls back to the bundle default.
     */
    private static function resolveTtl(mixed $subject): ?int
    {
        if ($subject === null) {
            return null;
        }

        if (is_int($subject) && $subject > 0) {
            return $subject;
        }

        return null;
    }
}
