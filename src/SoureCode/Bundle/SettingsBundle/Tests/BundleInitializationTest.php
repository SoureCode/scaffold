<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\SettingsBundle\SettingsBundle;
use SoureCode\Bundle\SettingsBundle\Twig\SettingsExtension;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Factory\SettingFactory;
use SoureCode\Component\Settings\Factory\SettingFactoryInterface;
use SoureCode\Component\Settings\Manager\DoctrineSettingsManager;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use SoureCode\Component\Settings\Model\Setting;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class BundleInitializationTest extends AbstractBundleTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestBundle(TwigBundle::class);
        $kernel->addTestBundle(SettingsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/bundle.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(SettingMappingDriver::class));
        self::assertTrue($container->has(SettingFactory::class));
        self::assertTrue($container->has(SettingFactoryInterface::class));
        self::assertTrue($container->has(DoctrineSettingsManager::class));
        self::assertTrue($container->has(SettingsManagerInterface::class));
        self::assertTrue($container->has(SettingsExtension::class));
    }

    public function testDefaultEntityClassIsWiredIntoTheManager(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(DoctrineSettingsManager::class);

        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('settingEntityClassName');

        self::assertSame(Setting::class, $property->getValue($manager));
    }
}
