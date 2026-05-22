<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\FeatureFlagsBundle\FeatureFlagsBundle;
use SoureCode\Bundle\FeatureFlagsBundle\Tests\Fixtures\StampedFeatureFlag;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\User;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedAuthorProvider;
use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Cross-bundle integration: a `FeatureFlag` entity that uses Timestampable
 * + Authorable traits is toggled through `FeatureFlagsManager::enable()`
 * / `disable()`. Stamping listeners fire on the underlying flush.
 */
final class CompositionTest extends AbstractBundleTestCase
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
        $kernel->addTestBundle(SecurityBundle::class);
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestBundle(FeatureFlagsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/composition.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testEnableStampsCreatedAtAndCreatedBy(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(StampedFeatureFlag::class),
        ]);

        $alice = new User('alice');
        $entityManager->persist($alice);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($alice);

        $flags = $container->get(FeatureFlagsManagerInterface::class);
        $flags->enable('checkout.v2');

        $entityManager->clear();
        $stored = $entityManager->getRepository(StampedFeatureFlag::class)->find('checkout.v2');

        self::assertInstanceOf(StampedFeatureFlag::class, $stored);
        self::assertTrue($stored->isEnabled());
        self::assertNotNull($stored->getCreatedAt());
        self::assertSame($alice->id, $stored->getCreatedBy()?->id);
    }

    public function testDisableAdvancesUpdatedAtAndUpdatedBy(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(StampedFeatureFlag::class),
        ]);

        $alice = new User('alice');
        $bob = new User('bob');
        $entityManager->persist($alice);
        $entityManager->persist($bob);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($alice);

        $flags = $container->get(FeatureFlagsManagerInterface::class);
        $flags->enable('checkout.v2');

        $provider->setAuthor($bob);
        $flags->disable('checkout.v2');

        $entityManager->clear();
        $stored = $entityManager->getRepository(StampedFeatureFlag::class)->find('checkout.v2');

        self::assertInstanceOf(StampedFeatureFlag::class, $stored);
        self::assertFalse($stored->isEnabled());
        self::assertSame($alice->id, $stored->getCreatedBy()?->id);
        self::assertSame($bob->id, $stored->getUpdatedBy()?->id);
        self::assertNotNull($stored->getUpdatedAt());
    }
}
