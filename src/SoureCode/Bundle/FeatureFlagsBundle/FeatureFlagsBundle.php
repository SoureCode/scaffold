<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle;

use SoureCode\Bundle\FeatureFlagsBundle\DependencyInjection\Compiler\FeatureFlagsMappingPass;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class FeatureFlagsBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('entity_class')
                    ->defaultValue(FeatureFlag::class)
                    ->cannotBeEmpty()
                    ->info('FQCN implementing ' . FeatureFlagInterface::class . '. Defaults to the shipped FeatureFlag model.')
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue('feature_flags')
                    ->cannotBeEmpty()
                    ->info('Doctrine table name for the configured FeatureFlag class.')
                ->end()
            ->end();
    }

    /**
     * @param array{entity_class: string, table_name: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!is_a($config['entity_class'], FeatureFlagInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'feature_flags.entity_class "%s" must implement %s.',
                $config['entity_class'],
                FeatureFlagInterface::class,
            ));
        }

        $builder->setParameter('sourecode.feature_flags.entity_class', $config['entity_class']);
        $builder->setParameter('sourecode.feature_flags.table_name', $config['table_name']);

        $container->import(__DIR__ . '/config/services.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new FeatureFlagsMappingPass());
    }
}
