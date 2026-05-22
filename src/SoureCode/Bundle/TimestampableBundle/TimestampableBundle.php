<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle;

use Doctrine\ORM\Events;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\PrioritizedListenerRegistrar;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TimestampableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('listener_priorities')
                    ->addDefaultsIfNotSet()
                    ->info('Doctrine event listener priorities. Higher numbers run first.')
                    ->children()
                        ->integerNode('pre_persist')->defaultValue(0)->end()
                        ->integerNode('on_flush')->defaultValue(0)->end()
                        ->integerNode('load_class_metadata')->defaultValue(0)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{listener_priorities: array{pre_persist: int, on_flush: int, load_class_metadata: int}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        PrioritizedListenerRegistrar::register($builder, TimestampableListener::class, [
            Events::prePersist => $config['listener_priorities']['pre_persist'],
            Events::onFlush => $config['listener_priorities']['on_flush'],
        ]);

        PrioritizedListenerRegistrar::register($builder, TimestampableMappingListener::class, [
            Events::loadClassMetadata => $config['listener_priorities']['load_class_metadata'],
        ]);
    }
}
