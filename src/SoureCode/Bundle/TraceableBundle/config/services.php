<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\TraceableBundle\EventListener\ConsoleTraceListener;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TraceContextFactory::class)->public();

    $services->set(TraceContextHolder::class)->public();

    $services->set(HttpTraceListener::class)
        ->args([
            service(TraceContextFactory::class),
            service(TraceContextHolder::class),
            'X-Request-Id',
            'X-Request-Id',
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => 1024])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onResponse', 'priority' => -1024]);

    $services->set(ConsoleTraceListener::class)
        ->args([
            service(TraceContextFactory::class),
            service(TraceContextHolder::class),
        ])
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::COMMAND, 'method' => 'onCommand', 'priority' => 1024]);

    $services->set(TraceContextMiddleware::class)
        ->args([
            service(TraceContextFactory::class),
            service(TraceContextHolder::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('messenger.middleware')
        ->public();
};
