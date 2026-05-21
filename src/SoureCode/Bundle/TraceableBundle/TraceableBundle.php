<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle;

use SoureCode\Bundle\TraceableBundle\EventListener\ConsoleTraceListener;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TraceableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('http')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('request_header')->defaultValue('X-Request-Id')->end()
                        ->scalarNode('response_header')->defaultValue('X-Request-Id')->end()
                        ->enumNode('accept_incoming')
                            ->values(['never', 'trusted', 'always'])
                            ->defaultValue('never')
                            ->info('When to honour an incoming request_header. "never" generates a fresh id, "trusted" honours it only from trusted proxies, "always" trusts every caller.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('console')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('env_var')->defaultValue('TRACE_ID')->end()
                    ->end()
                ->end()
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     http:      array{enabled: bool, request_header: ?string, response_header: ?string, accept_incoming: 'never'|'trusted'|'always'},
     *     console:   array{enabled: bool, env_var: ?string},
     *     messenger: array{enabled: bool},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        if (!$config['http']['enabled']) {
            $builder->removeDefinition(HttpTraceListener::class);
        } else {
            $builder->getDefinition(HttpTraceListener::class)
                ->setArgument('$requestHeader', $config['http']['request_header'])
                ->setArgument('$responseHeader', $config['http']['response_header'])
                ->setArgument('$acceptIncoming', $config['http']['accept_incoming']);
        }

        if (!$config['console']['enabled']) {
            $builder->removeDefinition(ConsoleTraceListener::class);
        } else {
            $builder->getDefinition(ConsoleTraceListener::class)
                ->setArgument('$envVar', $config['console']['env_var']);
        }

        if (!$config['messenger']['enabled']) {
            $builder->removeDefinition(TraceContextMiddleware::class);
        }
    }
}
