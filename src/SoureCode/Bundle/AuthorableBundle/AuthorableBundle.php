<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle;

use SoureCode\Bundle\AuthorableBundle\Security\SecurityAuthorProvider;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
use SoureCode\Component\Authorable\EventListener\AuthorableMappingListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AuthorableBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('author_provider')
                    ->defaultNull()
                    ->info('Service id implementing ' . AuthorProviderInterface::class . '. Defaults to SecurityAuthorProvider.')
                ->end()
                ->scalarNode('user_class')
                    ->defaultNull()
                    ->info('Concrete entity class used as ManyToOne target for every CreatedBy/UpdatedBy/ChangedBy binding. When null, the property\'s PHP type is used.')
                ->end()
            ->end();
    }

    /**
     * @param array{author_provider: ?string, user_class: ?string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/config/services.php');

        $providerId = $config['author_provider'] ?? SecurityAuthorProvider::class;

        $builder->setAlias(AuthorProviderInterface::class, $providerId);

        if ($config['user_class'] !== null) {
            $builder->getDefinition(AuthorableMappingListener::class)
                ->setArgument('$userClass', $config['user_class']);
        }
    }
}
