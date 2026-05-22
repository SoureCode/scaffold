<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\SettingsBundle\SettingsBundle;
use SoureCode\Component\Settings\Doctrine\SettingMappingDriver;
use SoureCode\Component\Settings\Manager\DoctrineSettingsManager;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use SoureCode\Component\Settings\Tests\Fixtures\CustomSetting;
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
        $kernel->addTestBundle(SettingsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/bundle.php');
        $kernel->addTestConfig(__DIR__ . '/config/custom_entity.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testParametersResolveToConfiguredEntity(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame(CustomSetting::class, $container->getParameter('sourecode.settings.entity_class'));
        self::assertSame('custom_settings', $container->getParameter('sourecode.settings.table_name'));
    }

    public function testMappingDriverIsConfiguredForTheCustomClass(): void
    {
        self::bootKernel();
        $driver = self::getContainer()->get(SettingMappingDriver::class);

        self::assertSame([CustomSetting::class], $driver->getAllClassNames());
        self::assertFalse($driver->isTransient(CustomSetting::class));
    }

    public function testManagerEntityClassPropertyMatchesConfig(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(DoctrineSettingsManager::class);

        $property = (new \ReflectionClass($manager))->getProperty('settingEntityClassName');

        self::assertSame(CustomSetting::class, $property->getValue($manager));
    }

    public function testRoundTripPersistsCustomEntity(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(CustomSetting::class),
        ]);

        $manager = $container->get(SettingsManagerInterface::class);
        $manager->set('palette', ['fg' => '#000']);

        $stored = $entityManager->getRepository(CustomSetting::class)->find('palette');

        self::assertInstanceOf(CustomSetting::class, $stored);
        self::assertSame(['fg' => '#000'], $stored->getValue());
    }

    public function testCustomTableNameIsRealisedInSchema(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(CustomSetting::class),
        ]);

        $schema = $entityManager->getConnection()->createSchemaManager()->introspectSchema();

        self::assertTrue($schema->hasTable('custom_settings'));
    }
}
