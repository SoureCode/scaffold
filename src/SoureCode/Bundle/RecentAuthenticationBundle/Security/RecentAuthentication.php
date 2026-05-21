<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthClearedEvent;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthMarkedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

final class RecentAuthentication
{
    private const string SESSION_KEY = '_recent_auth_at';
    private const string RETURN_KEY = '_recent_auth_return';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly int $ttlSeconds,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function mark(): void
    {
        $session = $this->requestStack->getSession();
        $session->migrate(true);
        $at = $this->clock->now()->getTimestamp();
        $session->set(self::SESSION_KEY, $at);

        $this->eventDispatcher?->dispatch(new RecentAuthMarkedEvent($at));
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
        $this->eventDispatcher?->dispatch(new RecentAuthClearedEvent());
    }

    public function isActive(?int $ttlSeconds = null): bool
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null || !$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();

        if (!$session->isStarted() || !$session->has(self::SESSION_KEY)) {
            return false;
        }

        $at = (int) $session->get(self::SESSION_KEY);
        $expiresAt = $at + ($ttlSeconds ?? $this->ttlSeconds);

        if ($expiresAt < $this->clock->now()->getTimestamp()) {
            if ($ttlSeconds === null) {
                $session->remove(self::SESSION_KEY);
                $this->eventDispatcher?->dispatch(new RecentAuthClearedEvent());
            }

            return false;
        }

        return true;
    }

    public function setReturnPath(string $path): void
    {
        if (!self::isSafeLocalPath($path)) {
            return;
        }

        $this->requestStack->getSession()->set(self::RETURN_KEY, $path);
    }

    public function takeReturnPath(): ?string
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::RETURN_KEY)) {
            return null;
        }

        $value = (string) $session->get(self::RETURN_KEY);
        $session->remove(self::RETURN_KEY);

        if (!self::isSafeLocalPath($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Local-only paths: must start with a single "/" and must not be
     * protocol-relative ("//...") or backslash-prefixed ("/\..."), which
     * some browsers normalize to host-relative URLs.
     */
    public static function isSafeLocalPath(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }

        if (isset($path[1]) && ($path[1] === '/' || $path[1] === '\\')) {
            return false;
        }

        return true;
    }
}
