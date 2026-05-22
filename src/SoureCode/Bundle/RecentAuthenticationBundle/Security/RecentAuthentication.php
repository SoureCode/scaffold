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
        $session = $this->resolveSession();

        if ($session === null) {
            return;
        }

        $session->migrate(true);
        $at = $this->clock->now()->getTimestamp();
        $session->set(self::SESSION_KEY, $at);

        $this->eventDispatcher?->dispatch(new RecentAuthMarkedEvent($at));
    }

    public function clear(): void
    {
        $session = $this->resolveSession();

        if ($session === null) {
            return;
        }

        $session->remove(self::SESSION_KEY);
        $this->eventDispatcher?->dispatch(new RecentAuthClearedEvent());
    }

    /**
     * Returns the active session, or null when called outside an HTTP
     * request (CLI, messenger worker) so `mark()` and `clear()` are
     * inert there instead of throwing SessionNotFoundException —
     * mirroring the defensiveness that `isActive()` already has.
     */
    private function resolveSession(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }

    /**
     * Checks whether the current session has a recent-auth marker that is
     * still within TTL.
     *
     * Side-effect asymmetry: an expired marker is auto-cleared **only**
     * when called without an explicit `$ttlSeconds` (i.e. against the
     * configured bundle default). A tighter per-call TTL is meant for a
     * per-resource sensitivity bar — denying access does not invalidate
     * the underlying session timestamp, because a less sensitive
     * resource may still accept it. Callers needing a hard wipe should
     * call `clear()` explicitly.
     */
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

        $session = $this->resolveSession();

        if ($session === null) {
            return;
        }

        $session->set(self::RETURN_KEY, $path);
    }

    public function takeReturnPath(): ?string
    {
        $session = $this->resolveSession();

        if ($session === null || !$session->has(self::RETURN_KEY)) {
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
