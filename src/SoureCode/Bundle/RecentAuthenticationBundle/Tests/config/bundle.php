<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container): void {
    $container->loadFromExtension('framework', [
        'test' => true,
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'php_errors' => ['log' => true],
        'router' => ['utf8' => true],
        'secret' => 'test',
        'session' => [
            'storage_factory_id' => 'session.storage.factory.mock_file',
        ],
    ]);

    $container->loadFromExtension('security', [
        'providers' => [
            'in_memory' => ['memory' => null],
        ],
        'firewalls' => [
            'main' => ['security' => false],
        ],
    ]);

    $container->loadFromExtension('twig', [
        'default_path' => __DIR__ . '/templates',
        'strict_variables' => true,
    ]);
};
