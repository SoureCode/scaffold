<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle;

use Doctrine\ORM\Events;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\ListenerPrioritiesConfigBuilder;
use SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection\PrioritizedListenerRegistrar;
use SoureCode\Bundle\LifecycleBundle\Security\SecurityAuthorProvider;
use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Component\Lifecycle\EventListener\AuthorableListener;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\EventListener\ImpersonatorListener;
use SoureCode\Component\Lifecycle\Doctrine\SoftDeleteFilter;
use SoureCode\Component\Lifecycle\EventListener\TimestampableListener;
use SoureCode\Component\Lifecycle\EventListener\TimestampableMappingListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Umbrella bundle exposing Authorable, Timestampable, and Removable behaviors
 * under one configuration root: `lifecycle.{authorable,timestampable,removable}`.
 *
 * The three behaviors ship together because they consistently appear together
 * on the same entity (created-by/at, updated-by/at, deleted-by/at) and one
 * install + one config root is friendlier than three.
 */
final class LifecycleBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('authorable')
                    ->addDefaultsIfNotSet()
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
                    ->end()
                ->end()
                ->arrayNode('timestampable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append(ListenerPrioritiesConfigBuilder::build([
                            'pre_persist' => 0,
                            'on_flush' => 0,
                            'load_class_metadata' => 0,
                        ]))
                    ->end()
                ->end()
                ->arrayNode('removable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('soft_delete_filter')
                            ->info('Doctrine SQL filter that appends "deletedAt IS NULL" to every SELECT whose root entity carries #[DeletedAt].')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('name')
                                    ->defaultValue('soft_delete')
                                    ->info('Filter name as registered on the Doctrine entity manager.')
                                    ->cannotBeEmpty()
                                ->end()
                                ->scalarNode('entity_manager')
                                    ->defaultValue('default')
                                    ->info('Doctrine entity manager to register the filter on.')
                                    ->cannotBeEmpty()
                                ->end()
                                ->booleanNode('enabled_by_default')
                                    ->defaultTrue()
                                    ->info('Whether the filter starts enabled on every request.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     authorable: array{
     *         author_provider: ?string,
     *         user_class: ?string,
     *         listener_priorities: array{pre_persist: int, on_flush: int, load_class_metadata: int},
     *     },
     *     timestampable: array{
     *         listener_priorities: array{pre_persist: int, on_flush: int, load_class_metadata: int},
     *     },
     *     removable: array{
     *         soft_delete_filter: array{enabled: bool, name: string, entity_manager: string, enabled_by_default: bool},
     *     },
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services_authorable.php');
        $container->import(__DIR__ . '/config/services_timestampable.php');
        $container->import(__DIR__ . '/config/services_removable.php');

        $authorable = $config['authorable'];
        $providerId = $authorable['author_provider'] ?? SecurityAuthorProvider::class;
        $builder->setAlias(AuthorProviderInterface::class, $providerId);

        if ($authorable['user_class'] !== null) {
            $builder->getDefinition(AuthorableMappingListener::class)
                ->setArgument('$userClass', $authorable['user_class']);
        }

        PrioritizedListenerRegistrar::register($builder, AuthorableListener::class, [
            Events::prePersist => $authorable['listener_priorities']['pre_persist'],
            Events::onFlush => $authorable['listener_priorities']['on_flush'],
        ]);

        PrioritizedListenerRegistrar::register($builder, ImpersonatorListener::class, [
            Events::prePersist => $authorable['listener_priorities']['pre_persist'],
        ]);

        PrioritizedListenerRegistrar::register($builder, AuthorableMappingListener::class, [
            Events::loadClassMetadata => $authorable['listener_priorities']['load_class_metadata'],
        ]);

        $timestampable = $config['timestampable'];

        PrioritizedListenerRegistrar::register($builder, TimestampableListener::class, [
            Events::prePersist => $timestampable['listener_priorities']['pre_persist'],
            Events::onFlush => $timestampable['listener_priorities']['on_flush'],
        ]);

        PrioritizedListenerRegistrar::register($builder, TimestampableMappingListener::class, [
            Events::loadClassMetadata => $timestampable['listener_priorities']['load_class_metadata'],
        ]);

        $softDelete = $config['removable']['soft_delete_filter'];
        $builder->setParameter('sourecode.lifecycle.removable.soft_delete_filter.enabled', $softDelete['enabled']);
        $builder->setParameter('sourecode.lifecycle.removable.soft_delete_filter.name', $softDelete['name']);
        $builder->setParameter('sourecode.lifecycle.removable.soft_delete_filter.entity_manager', $softDelete['entity_manager']);
        $builder->setParameter('sourecode.lifecycle.removable.soft_delete_filter.enabled_by_default', $softDelete['enabled_by_default']);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Merge every partial config the host loaded under `lifecycle:` —
        // tests stack soft_delete_filter under one config file and the
        // doctrine/authorable trees under another, so we cannot rely on
        // [0] holding the soft-delete entry.
        $filter = [];

        foreach ($builder->getExtensionConfig($this->extensionAlias) as $partial) {
            if (isset($partial['removable']['soft_delete_filter'])) {
                $filter = array_replace($filter, $partial['removable']['soft_delete_filter']);
            }
        }

        if (!($filter['enabled'] ?? false)) {
            return;
        }

        $container->extension('doctrine', [
            'orm' => [
                'entity_managers' => [
                    $filter['entity_manager'] ?? 'default' => [
                        'filters' => [
                            $filter['name'] ?? 'soft_delete' => [
                                'class' => SoftDeleteFilter::class,
                                'enabled' => $filter['enabled_by_default'] ?? true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
