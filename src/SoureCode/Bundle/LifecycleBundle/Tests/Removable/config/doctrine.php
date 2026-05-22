<?php

declare(strict_types=1);

use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Component\Lifecycle\Tests\Removable\Support\FixedAuthorProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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

    $container->loadFromExtension('lifecycle', [
        'authorable' => [
            'author_provider' => 'app.author_provider',
        ],
    ]);

    $container->setDefinition('app.author_provider', (new Definition(FixedAuthorProvider::class))->setPublic(true));
    $container->setAlias(AuthorProviderInterface::class, 'app.author_provider')->setPublic(true);
};
