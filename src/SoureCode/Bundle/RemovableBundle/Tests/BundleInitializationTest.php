<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\AuthorableBundle\AuthorableBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\RemovableBundle\RemovableBundle;
use SoureCode\Bundle\TimestampableBundle\TimestampableBundle;
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
        $kernel->addTestBundle(AuthorableBundle::class);
        $kernel->addTestBundle(RemovableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/doctrine.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testKernelBoots(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertNotNull($container);
    }
}
