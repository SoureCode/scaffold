<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\LifecycleBundle\Security\SecurityImpersonatorProvider;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\StubUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SecurityImpersonatorProviderTest extends TestCase
{
    public function testReturnsNullWhenSecurityServiceIsAbsent(): void
    {
        $provider = new SecurityImpersonatorProvider(null);

        self::assertNull($provider->getImpersonator());
    }

    public function testReturnsNullWhenNoTokenIsActive(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getToken')->willReturn(null);

        $provider = new SecurityImpersonatorProvider($security);

        self::assertNull($provider->getImpersonator());
    }

    public function testReturnsNullForNonSwitchUserTokens(): void
    {
        $token = new UsernamePasswordToken(new StubUser('alice'), 'main', ['ROLE_USER']);
        $security = $this->createStub(Security::class);
        $security->method('getToken')->willReturn($token);

        $provider = new SecurityImpersonatorProvider($security);

        self::assertNull($provider->getImpersonator());
    }

    public function testReturnsOriginalUserBehindSwitchUserToken(): void
    {
        $admin = new StubUser('admin');
        $alice = new StubUser('alice');

        $originalToken = new UsernamePasswordToken($admin, 'main', ['ROLE_ADMIN']);
        $switchToken = new SwitchUserToken($alice, 'main', ['ROLE_USER'], $originalToken);

        $security = $this->createStub(Security::class);
        $security->method('getToken')->willReturn($switchToken);

        $provider = new SecurityImpersonatorProvider($security);

        self::assertSame($admin, $provider->getImpersonator());
    }
}
