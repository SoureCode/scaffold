<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Component\Lifecycle\EventListener\AuthorableListener;
use SoureCode\Component\Lifecycle\EventListener\AuthorableMappingListener;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
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
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/doctrine.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(AuthorableMetadataFactory::class));
        self::assertTrue($container->has(ChangeSetMatcher::class));
        self::assertTrue($container->has(AuthorableListener::class));
        self::assertTrue($container->has(AuthorableMappingListener::class));
        self::assertTrue($container->has(AuthorProviderInterface::class));
    }
}
