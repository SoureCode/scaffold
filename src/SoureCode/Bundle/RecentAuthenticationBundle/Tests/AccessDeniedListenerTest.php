<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\RecentAuthenticationBundle\Event\RecentAuthRequiredEvent;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\AccessDeniedListener;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use SoureCode\Bundle\RecentAuthenticationBundle\Tests\Support\RecordingEventDispatcher;
use SoureCode\Bundle\RecentAuthenticationBundle\Tests\Support\StaticRedirectStrategy;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedListenerTest extends TestCase
{
    private RecordingEventDispatcher $dispatcher;
    private RequestStack $stack;
    private AccessDeniedListener $listener;

    protected function setUp(): void
    {
        $this->dispatcher = new RecordingEventDispatcher();
        $this->stack = new RequestStack();
        $recent = new RecentAuthentication($this->stack, new MockClock(), 900, $this->dispatcher);

        $this->listener = new AccessDeniedListener(
            $recent,
            new StaticRedirectStrategy(),
            $this->dispatcher,
        );
    }

    public function testGetRequestRecordsReturnPathAndDispatchesRequiredEvent(): void
    {
        $request = $this->pushRequest('/account/billing');

        $event = $this->exceptionEvent($request);
        ($this->listener)($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame('/login', $event->getResponse()->getTargetUrl());

        $required = $this->lastRequiredEvent();
        self::assertNotNull($required);
        self::assertSame('/account/billing', $required->returnPath);
    }

    public function testPostRequestDoesNotStoreReturnPath(): void
    {
        $request = $this->pushRequest('/account/billing', 'POST');

        $event = $this->exceptionEvent($request);
        ($this->listener)($event);

        $required = $this->lastRequiredEvent();
        self::assertNotNull($required, 'event still fires so subscribers can audit');
        self::assertNull($required->returnPath, 'POST is not a safe method to redirect back to');
    }

    public function testOtherAccessDeniedAttributesAreIgnored(): void
    {
        $request = $this->pushRequest('/account/billing');

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->deniedWithAttributes(['ROLE_ADMIN']),
        );

        ($this->listener)($event);

        self::assertNull($event->getResponse());
        self::assertNull($this->lastRequiredEvent());
    }

    private function pushRequest(string $uri, string $method = 'GET'): Request
    {
        $request = Request::create($uri, $method);
        $request->setSession($this->session());
        $this->stack->push($request);

        return $request;
    }

    private function session(): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        return $session;
    }

    private function exceptionEvent(Request $request): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->deniedWithAttributes([RecentAuthenticationVoter::IS_AUTHENTICATED_RECENTLY]),
        );
    }

    /**
     * @param list<string> $attributes
     */
    private function deniedWithAttributes(array $attributes): AccessDeniedException
    {
        $exception = new AccessDeniedException('denied');
        $exception->setAttributes($attributes);

        return $exception;
    }

    private function lastRequiredEvent(): ?RecentAuthRequiredEvent
    {
        foreach (array_reverse($this->dispatcher->events) as $event) {
            if ($event instanceof RecentAuthRequiredEvent) {
                return $event;
            }
        }

        return null;
    }
}
