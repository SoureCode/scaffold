<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter;

use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class RecentAuthenticationVoter extends Voter
{
    public const string IS_AUTHENTICATED_RECENTLY = 'IS_AUTHENTICATED_RECENTLY';

    public function __construct(
        private readonly RecentAuthentication $recentAuthentication,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::IS_AUTHENTICATED_RECENTLY;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->recentAuthentication->isActive();
    }
}
