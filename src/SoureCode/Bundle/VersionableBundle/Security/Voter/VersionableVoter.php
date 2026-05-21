<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Skeleton voter for restricting Versionable read / restore.
 *
 * Ships denying-by-default so consumers wire their own logic on top with a
 * higher-priority voter or by subclassing. Symfony's default access decision
 * strategy ("affirmative") will defer to whatever the consumer voter says.
 *
 * @extends Voter<string, object|class-string|null>
 */
class VersionableVoter extends Voter
{
    public const string VERSION_VIEW = 'VERSION_VIEW';
    public const string VERSION_RESTORE = 'VERSION_RESTORE';
    public const string VERSION_PRUNE = 'VERSION_PRUNE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VERSION_VIEW,
            self::VERSION_RESTORE,
            self::VERSION_PRUNE,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return false;
    }
}
