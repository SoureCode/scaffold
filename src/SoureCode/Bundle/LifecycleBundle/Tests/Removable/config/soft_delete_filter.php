<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container): void {
    $container->loadFromExtension('lifecycle', [
        'removable' => [
            'soft_delete_filter' => [
                'enabled' => true,
            ],
        ],
    ]);
};
