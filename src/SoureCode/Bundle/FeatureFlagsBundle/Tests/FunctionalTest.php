<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\FeatureFlagsBundle\FeatureFlagsBundle;
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
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
        $kernel->addTestBundle(FeatureFlagsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/bundle.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testEnableDisableRoundTripThroughTheManager(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(FeatureFlag::class),
        ]);

        $manager = $container->get(FeatureFlagsManagerInterface::class);

        self::assertFalse($manager->isEnabled('beta'));

        $manager->enable('beta');
        self::assertTrue($manager->isEnabled('beta'));

        $manager->disable('beta');
        self::assertFalse($manager->isEnabled('beta'));
        self::assertTrue($manager->has('beta'));

        $manager->remove('beta');
        self::assertFalse($manager->has('beta'));
    }

    public function testTwigFeatureEnabledRendersFlagState(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(FeatureFlag::class),
        ]);

        $container->get(FeatureFlagsManagerInterface::class)->enable('beta');

        $twig = $container->get('twig');

        self::assertSame(
            'on',
            $twig->createTemplate("{{ feature_enabled('beta') ? 'on' : 'off' }}")->render(),
        );

        self::assertSame(
            'off',
            $twig->createTemplate("{{ feature_enabled('missing') ? 'on' : 'off' }}")->render(),
        );
    }
}
