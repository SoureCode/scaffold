<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\FeatureFlagsBundle\FeatureFlagsBundle;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Manager\DoctrineFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use SoureCode\Component\FeatureFlags\Tests\Fixtures\CustomFeatureFlag;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class CustomEntityConfigTest extends AbstractBundleTestCase
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
        $kernel->addTestConfig(__DIR__ . '/config/custom_entity.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testParametersResolveToConfiguredEntity(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame(CustomFeatureFlag::class, $container->getParameter('sourecode.feature_flags.entity_class'));
        self::assertSame('custom_flags', $container->getParameter('sourecode.feature_flags.table_name'));
    }

    public function testMappingDriverIsConfiguredForTheCustomClass(): void
    {
        self::bootKernel();
        $driver = self::getContainer()->get(FeatureFlagMappingDriver::class);

        self::assertSame([CustomFeatureFlag::class], $driver->getAllClassNames());
        self::assertFalse($driver->isTransient(CustomFeatureFlag::class));
    }

    public function testFactoryCreatesCustomEntity(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(FeatureFlagFactory::class);

        $flag = $factory->create('alpha');

        self::assertInstanceOf(CustomFeatureFlag::class, $flag);
        self::assertSame('alpha', $flag->getName());
    }

    public function testManagerEntityClassPropertyMatchesConfig(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(DoctrineFeatureFlagsManager::class);

        $property = (new \ReflectionClass($manager))->getProperty('featureFlagEntityClassName');

        self::assertSame(CustomFeatureFlag::class, $property->getValue($manager));
    }

    public function testRoundTripPersistsCustomEntity(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(CustomFeatureFlag::class),
        ]);

        $manager = $container->get(FeatureFlagsManagerInterface::class);
        $manager->enable('alpha');

        $stored = $entityManager->getRepository(CustomFeatureFlag::class)->find('alpha');

        self::assertInstanceOf(CustomFeatureFlag::class, $stored);
        self::assertTrue($stored->isEnabled());
    }

    public function testCustomTableNameIsRealisedInSchema(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(CustomFeatureFlag::class),
        ]);

        $schema = $entityManager->getConnection()->createSchemaManager()->introspectSchema();

        self::assertTrue($schema->hasTable('custom_flags'));
    }
}
