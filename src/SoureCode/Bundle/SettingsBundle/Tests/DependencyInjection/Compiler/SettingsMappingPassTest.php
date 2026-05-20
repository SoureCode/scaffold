<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\SettingsBundle\DependencyInjection\Compiler\SettingsMappingPass;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SettingsMappingPassTest extends TestCase
{
    public function testProcessRegistersDriverForDefaultModelNamespace(): void
    {
        $container = $this->makeContainer(Setting::class);

        (new SettingsMappingPass())->process($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);

        [$method, $arguments] = $calls[0];
        self::assertSame('addDriver', $method);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(SettingMappingDriver::class, (string) $arguments[0]);
        self::assertSame('SoureCode\\Component\\Settings\\Model', $arguments[1]);
    }

    public function testProcessRegistersDriverForCustomEntityNamespace(): void
    {
        $container = $this->makeContainer(CustomSetting::class);

        (new SettingsMappingPass())->process($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame('SoureCode\\Component\\Settings\\Tests\\Fixtures', $calls[0][1][1]);
    }

    public function testProcessIsNoopWhenMetadataChainIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sourecode.settings.entity_class', Setting::class);

        (new SettingsMappingPass())->process($container);

        self::assertFalse($container->hasDefinition('doctrine.orm.default_metadata_driver'));
    }

    /**
     * @param class-string $entityClass
     */
    private function makeContainer(string $entityClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('sourecode.settings.entity_class', $entityClass);
        $container->setDefinition('doctrine.orm.default_metadata_driver', new Definition(\stdClass::class));

        return $container;
    }
}
