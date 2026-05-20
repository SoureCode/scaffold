<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class RecentAuthentication
{
    private const string SESSION_KEY = '_recent_auth_until';
    private const string RETURN_KEY = '_recent_auth_return';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly int $ttlSeconds,
    ) {}

    public function mark(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $this->clock->now()->getTimestamp() + $this->ttlSeconds);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    public function isActive(): bool
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null || !$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();

        if (!$session->isStarted() || !$session->has(self::SESSION_KEY)) {
            return false;
        }

        $until = (int) $session->get(self::SESSION_KEY);

        if ($until < $this->clock->now()->getTimestamp()) {
            $session->remove(self::SESSION_KEY);

            return false;
        }

        return true;
    }

    public function setReturnPath(string $path): void
    {
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

        return $value;
    }
}
