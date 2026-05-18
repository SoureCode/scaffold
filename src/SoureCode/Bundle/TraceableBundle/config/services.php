<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\TraceableBundle\EventListener\ConsoleTraceListener;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TraceContextFactory::class)->public();

    $services->set(TraceContext::class)
        ->factory([service(TraceContextFactory::class), 'create'])
        ->public();

    $services->alias(TraceContextInterface::class, TraceContext::class)->public();

    $services->set(HttpTraceListener::class)
        ->args([
            service(TraceContextFactory::class),
            service('service_container'),
            'X-Request-Id',
            'X-Request-Id',
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => 1024])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse', 'priority' => -1024]);

    $services->set(ConsoleTraceListener::class)
        ->args([
            service(TraceContextFactory::class),
            service('service_container'),
        ])
        ->tag('kernel.event_listener', ['event' => 'console.command', 'method' => 'onCommand', 'priority' => 1024]);

    $services->set(TraceContextMiddleware::class)
        ->args([
            service(TraceContextFactory::class),
            service('service_container'),
        ])
        ->tag('messenger.middleware')
        ->public();
};
