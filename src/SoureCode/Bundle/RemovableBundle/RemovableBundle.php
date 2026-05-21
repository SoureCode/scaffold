<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle;

use SoureCode\Component\Removable\Doctrine\SoftDeleteFilter;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RemovableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
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
            ->end();
    }

    /**
     * @param array{soft_delete_filter: array{enabled: bool, name: string, entity_manager: string, enabled_by_default: bool}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        $builder->setParameter('sourecode.removable.soft_delete_filter.enabled', $config['soft_delete_filter']['enabled']);
        $builder->setParameter('sourecode.removable.soft_delete_filter.name', $config['soft_delete_filter']['name']);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $builder->getExtensionConfig($this->extensionAlias)[0] ?? [];

        $filter = $config['soft_delete_filter'] ?? [];

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
