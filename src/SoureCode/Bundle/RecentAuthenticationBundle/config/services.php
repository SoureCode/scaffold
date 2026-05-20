<?php

declare(strict_types=1);

use Psr\Clock\ClockInterface;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\AccessDeniedListener;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\LoginSuccessListener;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use SoureCode\Bundle\RecentAuthenticationBundle\Twig\RecentAuthenticationExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RecentAuthentication::class)
        ->args([
            service(RequestStack::class),
            service(ClockInterface::class),
            0,
        ]);

    $services->set(RecentAuthenticationVoter::class)
        ->args([service(RecentAuthentication::class)])
        ->tag('security.voter');

    $services->set(LoginSuccessListener::class)
        ->args([service(RecentAuthentication::class)])
        ->tag('kernel.event_listener', [
            'event' => LoginSuccessEvent::class,
            'method' => '__invoke',
        ]);

    $services->set(AccessDeniedListener::class)
        ->args([
            service(RecentAuthentication::class),
            service(UrlGeneratorInterface::class),
            '',
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
