<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\DependencyInjection\Compiler;

use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class FeatureFlagsMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.orm.default_metadata_driver')) {
            return;
        }

        $entityClass = (string) $container->getParameter('sourecode.feature_flags.entity_class');
        $namespace = (new \ReflectionClass($entityClass))->getNamespaceName();

        $container->getDefinition('doctrine.orm.default_metadata_driver')
            ->addMethodCall('addDriver', [
                new Reference(FeatureFlagMappingDriver::class),
                $namespace,
            ]);
    }
}
