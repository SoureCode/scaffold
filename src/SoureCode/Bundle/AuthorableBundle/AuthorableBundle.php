<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle;

use Doctrine\ORM\Events;
use SoureCode\Bundle\AuthorableBundle\Security\SecurityAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\ListenerPrioritiesConfigBuilder;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\PrioritizedListenerRegistrar;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\EventListener\AuthorableListener;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use SoureCode\Component\Authorable\EventListener\ImpersonatorListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AuthorableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('author_provider')
                    ->defaultNull()
                    ->info('Service id implementing ' . AuthorProviderInterface::class . '. Defaults to SecurityAuthorProvider.')
                ->end()
                ->scalarNode('user_class')
                    ->defaultNull()
                    ->info('Concrete entity class used as ManyToOne target for every CreatedBy/UpdatedBy/ChangedBy binding. When null, the property\'s PHP type is used.')
                ->end()
                ->append(ListenerPrioritiesConfigBuilder::build([
                    'pre_persist' => 0,
                    'on_flush' => 0,
                    'load_class_metadata' => 0,
                ]))
            ->end();
    }

    /**
     * @param array{
     *     author_provider: ?string,
     *     user_class: ?string,
     *     listener_priorities: array{pre_persist: int, on_flush: int, load_class_metadata: int},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        $providerId = $config['author_provider'] ?? SecurityAuthorProvider::class;

        $builder->setAlias(AuthorProviderInterface::class, $providerId);

        if ($config['user_class'] !== null) {
            $builder->getDefinition(AuthorableMappingListener::class)
                ->setArgument('$userClass', $config['user_class']);
        }

        PrioritizedListenerRegistrar::register($builder, AuthorableListener::class, [
            Events::prePersist => $config['listener_priorities']['pre_persist'],
            Events::onFlush => $config['listener_priorities']['on_flush'],
        ]);

        PrioritizedListenerRegistrar::register($builder, ImpersonatorListener::class, [
            Events::prePersist => $config['listener_priorities']['pre_persist'],
        ]);

        PrioritizedListenerRegistrar::register($builder, AuthorableMappingListener::class, [
            Events::loadClassMetadata => $config['listener_priorities']['load_class_metadata'],
        ]);
    }
}
