<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\FeatureFlagsBundle\FeatureFlagsBundle;
use SoureCode\Bundle\FeatureFlagsBundle\Twig\FeatureFlagsExtension;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactoryInterface;
use SoureCode\Component\FeatureFlags\Manager\DoctrineFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
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
        $kernel->addTestBundle(FeatureFlagsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/bundle.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(FeatureFlagMappingDriver::class));
        self::assertTrue($container->has(FeatureFlagFactory::class));
        self::assertTrue($container->has(FeatureFlagFactoryInterface::class));
        self::assertTrue($container->has(DoctrineFeatureFlagsManager::class));
        self::assertTrue($container->has(FeatureFlagsManagerInterface::class));
        self::assertTrue($container->has(FeatureFlagsExtension::class));
    }

    public function testDefaultEntityClassIsWiredIntoTheManager(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(DoctrineFeatureFlagsManager::class);

        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('featureFlagEntityClassName');

        self::assertSame(FeatureFlag::class, $property->getValue($manager));
    }
}
