<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class RecentAuthenticationTest extends TestCase
{
    public function testIsActiveReturnsFalseWhenNoRequestIsActive(): void
    {
        $requestStack = new RequestStack();
        $clock = new MockClock('2026-05-20 12:00:00');

        $service = new RecentAuthentication($requestStack, $clock, 900);

        self::assertFalse($service->isActive());
    }

    public function testMarkAndIsActiveWithinTtl(): void
    {
        [$service, $clock] = $this->makeServiceWithSession();

        $service->mark();
        self::assertTrue($service->isActive());

        $clock->modify('+899 seconds');
        self::assertTrue($service->isActive());
    }

    public function testIsActiveExpiresAfterTtl(): void
    {
        [$service, $clock] = $this->makeServiceWithSession();

        $service->mark();
        $clock->modify('+901 seconds');

        self::assertFalse($service->isActive());
    }

    public function testClearRemovesActiveMark(): void
    {
        [$service] = $this->makeServiceWithSession();

        $service->mark();
        $service->clear();

        self::assertFalse($service->isActive());
    }

    public function testReturnPathLifecycle(): void
    {
        [$service] = $this->makeServiceWithSession();

        self::assertNull($service->takeReturnPath());

        $service->setReturnPath('/settings/change-password');

        self::assertSame('/settings/change-password', $service->takeReturnPath());
        self::assertNull($service->takeReturnPath());
    }

    #[DataProvider('unsafeReturnPaths')]
    public function testSetReturnPathRejectsUnsafePath(string $path): void
    {
        [$service] = $this->makeServiceWithSession();

        $service->setReturnPath($path);

        self::assertNull($service->takeReturnPath());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeReturnPaths(): iterable
    {
        yield 'protocol relative' => ['//attacker.example/path'];
        yield 'backslash relative' => ['/\\attacker.example/path'];
        yield 'absolute http' => ['http://attacker.example/path'];
        yield 'absolute https' => ['https://attacker.example/path'];
        yield 'empty' => [''];
        yield 'no leading slash' => ['settings/profile'];
    }

    public function testIsActivePerAttributeTtlOverridesDefault(): void
    {
        [$service, $clock] = $this->makeServiceWithSession();

        $service->mark();
        $clock->modify('+120 seconds');

        self::assertFalse($service->isActive(60), 'tight ttl must reject when older than override');
        self::assertTrue($service->isActive(), 'default ttl is still in window');
    }

    /**
     * @return array{0: RecentAuthentication, 1: MockClock}
     */
    private function makeServiceWithSession(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $clock = new MockClock('2026-05-20 12:00:00');

        return [new RecentAuthentication($requestStack, $clock, 900), $clock];
    }
}
