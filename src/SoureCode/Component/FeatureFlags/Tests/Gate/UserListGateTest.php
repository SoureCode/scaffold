<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Gate;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Gate\UserListGate;

final class UserListGateTest extends TestCase
{
    public function testAllowListMatchReturnsTrue(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['allow' => ['alice', 'bob']]]);

        self::assertTrue($gate->decide('checkout.v2', ['user_id' => 'alice']));
    }

    public function testDenyListMatchReturnsFalse(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['deny' => ['mallory']]]);

        self::assertFalse($gate->decide('checkout.v2', ['user_id' => 'mallory']));
    }

    public function testAllowWinsOverDenyWhenBothMatch(): void
    {
        $gate = new UserListGate([
            'checkout.v2' => ['allow' => ['alice'], 'deny' => ['alice']],
        ]);

        self::assertTrue($gate->decide('checkout.v2', ['user_id' => 'alice']));
    }

    public function testUnknownFlagAbstains(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['allow' => ['alice']]]);

        self::assertNull($gate->decide('other.flag', ['user_id' => 'alice']));
    }

    public function testNoUserIdAbstains(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['allow' => ['alice']]]);

        self::assertNull($gate->decide('checkout.v2'));
    }

    public function testUserIdCoercedToString(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['allow' => ['42']]]);

        self::assertTrue($gate->decide('checkout.v2', ['user_id' => 42]));
    }

    public function testKnownFlagWithUserOnNeitherListAbstains(): void
    {
        $gate = new UserListGate(['checkout.v2' => ['allow' => ['alice'], 'deny' => ['mallory']]]);

        self::assertNull($gate->decide('checkout.v2', ['user_id' => 'carol']));
    }
}
