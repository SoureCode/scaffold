<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle;

use SoureCode\Bundle\AuthorableBundle\Security\SecurityAuthorProvider;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AuthorableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('author_provider')
                    ->defaultNull()
                    ->info('Service id implementing ' . AuthorProviderInterface::class . '. Defaults to SecurityAuthorProvider when symfony/security-bundle is installed.')
                ->end()
            ->end();
    }

    /**
     * @param array{author_provider: ?string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        $providerId = $config['author_provider'];

        if ($providerId === null) {
            if (!class_exists(Security::class)) {
                throw new \LogicException('AuthorableBundle: "author_provider" is required when symfony/security-bundle is not installed.');
            }

            $providerId = SecurityAuthorProvider::class;
            $builder->setDefinition(
                $providerId,
                (new Definition($providerId))->setArguments([new Reference(Security::class)]),
            );
        }

        $builder->setAlias(AuthorProviderInterface::class, $providerId);
    }
}
