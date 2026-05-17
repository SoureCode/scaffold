<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\TimestampableBundle\TimestampableBundle;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Clock\TimestampFactory;
use SoureCode\Component\Timestampable\EventListener\TimestampableListener;
use SoureCode\Component\Timestampable\EventListener\TimestampableMappingListener;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
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
        $kernel->addTestBundle(TimestampableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/doctrine.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(TimestampableMetadataFactory::class));
        self::assertTrue($container->has(ChangeSetMatcher::class));
        self::assertTrue($container->has(TimestampFactory::class));
        self::assertTrue($container->has(TimestampableListener::class));
        self::assertTrue($container->has(TimestampableMappingListener::class));
    }
}
