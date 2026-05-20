<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\SettingsBundle\SettingsBundle;
use SoureCode\Component\Settings\Model\Setting;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class FunctionalTest extends AbstractBundleTestCase
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

    public function testSetAndGetRoundTripThroughTheManager(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Setting::class),
        ]);

        $manager = $container->get(SettingsManagerInterface::class);

        $manager->set('site.title', 'Hello');
        self::assertSame('Hello', $manager->get('site.title'));

        $manager->set('flags.beta', true);
        self::assertTrue($manager->get('flags.beta'));

        $manager->remove('site.title');
        self::assertFalse($manager->has('site.title'));
    }

    public function testTwigSettingFunctionRendersConfiguredValue(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Setting::class),
        ]);

        $container->get(SettingsManagerInterface::class)->set('site.title', 'Hello Twig');

        $twig = $container->get('twig');

        self::assertSame(
            'Hello Twig',
            $twig->createTemplate("{{ setting('site.title') }}")->render(),
        );

        self::assertSame(
            'fallback',
            $twig->createTemplate("{{ setting('missing', 'fallback') }}")->render(),
        );
    }
}
