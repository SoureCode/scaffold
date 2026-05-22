<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests\Security\Voter;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\VersionableBundle\Security\Voter\VersionableVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VersionableVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function supportedAttributes(): iterable
    {
        yield 'view' => [VersionableVoter::VERSION_VIEW];
        yield 'restore' => [VersionableVoter::VERSION_RESTORE];
        yield 'prune' => [VersionableVoter::VERSION_PRUNE];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedAttributes')]
    public function testSupportsKnownAttributesAndAlwaysDeniesByDefault(string $attribute): void
    {
        $voter = new VersionableVoter();
        $token = $this->createStub(TokenInterface::class);

        $result = $voter->vote($token, null, [$attribute]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $voter = new VersionableVoter();
        $token = $this->createStub(TokenInterface::class);

        $result = $voter->vote($token, null, ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
