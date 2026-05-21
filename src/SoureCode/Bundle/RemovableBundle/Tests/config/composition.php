<?php

declare(strict_types=1);

use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\User;
use SoureCode\Bundle\AuthorableBundle\Tests\Support\FixedAuthorProvider;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

return static function (ContainerBuilder $container): void {
    $container->loadFromExtension('framework', [
        'test' => true,
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'php_errors' => ['log' => true],
        'router' => ['utf8' => true],
        'secret' => 'test',
    ]);

    $container->loadFromExtension('security', [
        'providers' => [
            'in_memory' => ['memory' => null],
        ],
        'firewalls' => [
            'main' => ['security' => false],
        ],
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
                'default' => [
                    'auto_mapping' => true,
                    'mappings' => [
                        'AuthorableUser' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../../../../Bundle/AuthorableBundle/Tests/Fixtures',
                            'prefix' => 'SoureCode\\Bundle\\AuthorableBundle\\Tests\\Fixtures',
                            'is_bundle' => false,
                        ],
                        'CompositionFixtures' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../Fixtures',
                            'prefix' => 'SoureCode\\Bundle\\RemovableBundle\\Tests\\Fixtures',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $container->loadFromExtension('authorable', [
        'user_class' => User::class,
        'author_provider' => 'app.author_provider',
    ]);

    $container->setDefinition('app.author_provider', (new Definition(FixedAuthorProvider::class))->setPublic(true));
    $container->setAlias(AuthorProviderInterface::class, 'app.author_provider')->setPublic(true);
};
