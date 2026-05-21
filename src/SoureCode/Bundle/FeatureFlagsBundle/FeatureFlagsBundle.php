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
                ->arrayNode('env_override')
                    ->addDefaultsIfNotSet()
                    ->info('Whether environment variables can short-circuit a flag lookup. The decorator always sits in front of the Doctrine manager; when "enabled" is false, env vars are simply ignored and every read falls through to Doctrine.')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Consult env vars before Doctrine when reading. When false, Doctrine is the sole read source. Writes always go to Doctrine either way.')
                        ->end()
                        ->scalarNode('prefix')
                            ->defaultValue('FEATURE_')
                            ->info('Prefix prepended to the uppercased flag name when looking up the env var.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{entity_class: string, table_name: string, env_override: array{enabled: bool, prefix: string}} $config
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
        $builder->setParameter('sourecode.feature_flags.env_override.enabled', $config['env_override']['enabled']);
        $builder->setParameter('sourecode.feature_flags.env_override.prefix', $config['env_override']['prefix']);

        $container->import(__DIR__ . '/config/services.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new FeatureFlagsMappingPass());
    }
}
