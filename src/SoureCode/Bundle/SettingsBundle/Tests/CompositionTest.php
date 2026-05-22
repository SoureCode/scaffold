<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\User;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\SettingsBundle\SettingsBundle;
use SoureCode\Bundle\SettingsBundle\Tests\Fixtures\StampedSetting;
use SoureCode\Component\Settings\Manager\SettingsManagerInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Cross-bundle integration: a `Setting` entity that uses Timestampable +
 * Authorable traits is persisted through `SettingsManager::set()`. Both
 * listener stacks fire on the underlying flush, producing a fully-stamped
 * row.
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
        $kernel->addTestBundle(TwigBundle::class);
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestBundle(SettingsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/composition.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testSettingsWriteStampsCreatedAtAndCreatedBy(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(StampedSetting::class),
        ]);

        $alice = new User('alice');
        $entityManager->persist($alice);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($alice);

        $settings = $container->get(SettingsManagerInterface::class);
        $settings->set('site.title', 'hello');

        $entityManager->clear();
        $stored = $entityManager->getRepository(StampedSetting::class)->find('site.title');

        self::assertInstanceOf(StampedSetting::class, $stored);
        self::assertSame('hello', $stored->getValue());
        self::assertNotNull($stored->getCreatedAt(), 'Timestampable stamped createdAt on the Setting');
        self::assertSame($alice->id, $stored->getCreatedBy()?->id, 'Authorable stamped createdBy on the Setting');
    }

    public function testSecondWriteUpdatesUpdatedAtAndUpdatedBy(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(StampedSetting::class),
        ]);

        $alice = new User('alice');
        $bob = new User('bob');
        $entityManager->persist($alice);
        $entityManager->persist($bob);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);
        $provider->setAuthor($alice);

        $settings = $container->get(SettingsManagerInterface::class);
        $settings->set('site.title', 'first');

        $provider->setAuthor($bob);
        $settings->set('site.title', 'second');

        $entityManager->clear();
        $stored = $entityManager->getRepository(StampedSetting::class)->find('site.title');

        self::assertInstanceOf(StampedSetting::class, $stored);
        self::assertSame('second', $stored->getValue());
        self::assertSame($alice->id, $stored->getCreatedBy()?->id, 'CreatedBy is never overwritten');
        self::assertSame($bob->id, $stored->getUpdatedBy()?->id, 'UpdatedBy advances to the active author');
        self::assertNotNull($stored->getUpdatedAt(), 'UpdatedAt is stamped on every change');
    }
}
