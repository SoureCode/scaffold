<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\AccessDeniedListener;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\LoginSuccessListener;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RedirectStrategyInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RouteRedirectStrategy;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use SoureCode\Bundle\RecentAuthenticationBundle\Twig\RecentAuthenticationExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RecentAuthentication::class)
        ->args([
            service(RequestStack::class),
            service(ClockInterface::class),
            '%sourecode.recent_authentication.ttl%',
            service(EventDispatcherInterface::class)->nullOnInvalid(),
        ]);

    $services->set(RecentAuthenticationVoter::class)
        ->args([
            service(RecentAuthentication::class),
            service(AuthenticationTrustResolverInterface::class)->nullOnInvalid(),
            '%sourecode.recent_authentication.require_full_authentication%',
        ])
        ->tag('security.voter');

    $services->set(RouteRedirectStrategy::class)
        ->args([
            service(UrlGeneratorInterface::class),
            '%sourecode.recent_authentication.login_route%',
        ]);

    $services->alias(RedirectStrategyInterface::class, RouteRedirectStrategy::class);

    $services->set(LoginSuccessListener::class)
        ->args([service(RecentAuthentication::class)])
        ->tag('kernel.event_listener', [
            'event' => LoginSuccessEvent::class,
            'method' => '__invoke',
        ]);

    $services->set(AccessDeniedListener::class)
        ->args([
            service(RecentAuthentication::class),
            service(RedirectStrategyInterface::class),
            service(EventDispatcherInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', [
            'event' => ExceptionEvent::class,
            'method' => '__invoke',
            'priority' => 2,
        ]);

    $services->set(RecentAuthenticationExtension::class)
        ->args([service(RecentAuthentication::class)])
        ->autoconfigure();
};
