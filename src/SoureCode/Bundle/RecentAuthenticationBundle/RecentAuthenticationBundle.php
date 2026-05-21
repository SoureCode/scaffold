<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RecentAuthenticationBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('ttl')
                    ->defaultValue(900)
                    ->min(1)
                    ->info('How long, in seconds, a recent authentication remains valid after the user re-confirms credentials.')
                ->end()
                ->scalarNode('login_route')
                    ->defaultValue('app_login')
                    ->cannotBeEmpty()
                    ->info('Route name the default RouteRedirectStrategy redirects to when IS_AUTHENTICATED_RECENTLY is required but not active.')
                ->end()
                ->booleanNode('require_full_authentication')
                    ->defaultTrue()
                    ->info('When true, remember-me-only tokens never count as recently-authenticated even within the TTL.')
                ->end()
            ->end();
    }

    /**
     * @param array{ttl: int, login_route: string, require_full_authentication: bool} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('sourecode.recent_authentication.ttl', $config['ttl']);
        $builder->setParameter('sourecode.recent_authentication.login_route', $config['login_route']);
        $builder->setParameter('sourecode.recent_authentication.require_full_authentication', $config['require_full_authentication']);

        $container->import(__DIR__ . '/config/services.php');
    }
}
