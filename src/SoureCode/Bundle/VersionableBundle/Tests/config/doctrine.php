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
    ]);

    $container->loadFromExtension('doctrine', [
        'dbal' => [
            'connections' => [
                'default' => ['url' => 'sqlite:///:memory:'],
            ],
        ],
        'orm' => [
            'enable_native_lazy_objects' => true,
            'entity_managers' => [
                'default' => ['auto_mapping' => true],
            ],
        ],
    ]);
};
