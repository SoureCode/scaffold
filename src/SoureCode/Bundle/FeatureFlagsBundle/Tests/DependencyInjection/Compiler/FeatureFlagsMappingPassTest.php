<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\FeatureFlagsBundle\DependencyInjection\Compiler\FeatureFlagsMappingPass;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class FeatureFlagsMappingPassTest extends TestCase
{
    public function testProcessRegistersDriverForDefaultModelNamespace(): void
    {
        $container = $this->makeContainer(FeatureFlag::class);

        (new FeatureFlagsMappingPass())->process($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);

        [$method, $arguments] = $calls[0];
        self::assertSame('addDriver', $method);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(FeatureFlagMappingDriver::class, (string) $arguments[0]);
        self::assertSame('SoureCode\\Component\\FeatureFlags\\Model', $arguments[1]);
    }

    public function testProcessRegistersDriverForCustomEntityNamespace(): void
    {
        $container = $this->makeContainer(CustomFeatureFlag::class);

        (new FeatureFlagsMappingPass())->process($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame('SoureCode\\Component\\FeatureFlags\\Tests\\Fixtures', $calls[0][1][1]);
    }

    public function testProcessIsNoopWhenMetadataChainIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sourecode.feature_flags.entity_class', FeatureFlag::class);

        (new FeatureFlagsMappingPass())->process($container);

        self::assertFalse($container->hasDefinition('doctrine.orm.default_metadata_driver'));
    }

    /**
     * @param class-string $entityClass
     */
    private function makeContainer(string $entityClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('sourecode.feature_flags.entity_class', $entityClass);
        $container->setDefinition('doctrine.orm.default_metadata_driver', new Definition(\stdClass::class));

        return $container;
    }
}
