<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\VersionableBundle\VersionableBundle;
use SoureCode\Component\Versionable\EventListener\VersionableListener;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
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
        $kernel->addTestBundle(VersionableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/doctrine.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(VersionableMetadataFactory::class));
        self::assertTrue($container->has(VersionableListener::class));
        self::assertTrue($container->has(VersionableSchemaListener::class));
    }
}
