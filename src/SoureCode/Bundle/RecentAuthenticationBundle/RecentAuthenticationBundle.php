<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle;

use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\AccessDeniedListener;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
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
                    ->info('Route name the AccessDeniedListener redirects to when IS_AUTHENTICATED_RECENTLY is required but not active.')
                ->end()
            ->end();
    }

    /**
     * @param array{ttl: int, login_route: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        $builder->getDefinition(RecentAuthentication::class)
            ->replaceArgument(2, $config['ttl']);

        $builder->getDefinition(AccessDeniedListener::class)
            ->replaceArgument(2, $config['login_route']);
    }
}
