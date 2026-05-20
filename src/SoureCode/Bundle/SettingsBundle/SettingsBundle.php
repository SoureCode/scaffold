<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle;

use SoureCode\Bundle\SettingsBundle\DependencyInjection\Compiler\SettingsMappingPass;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Model\SettingInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SettingsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('entity_class')
                    ->defaultValue(Setting::class)
                    ->cannotBeEmpty()
                    ->info('FQCN implementing ' . SettingInterface::class . '. Defaults to the shipped Setting model.')
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue('settings')
                    ->cannotBeEmpty()
                    ->info('Doctrine table name for the configured Setting class.')
                ->end()
            ->end();
    }

    /**
     * @param array{entity_class: string, table_name: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!is_a($config['entity_class'], SettingInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'settings.entity_class "%s" must implement %s.',
                $config['entity_class'],
                SettingInterface::class,
            ));
        }

        $builder->setParameter('sourecode.settings.entity_class', $config['entity_class']);
        $builder->setParameter('sourecode.settings.table_name', $config['table_name']);

        $container->import(__DIR__ . '/config/services.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new SettingsMappingPass());
    }
}
