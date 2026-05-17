<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class BundleInitializationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/framework.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testChangeSetMatcherIsRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(ChangeSetMatcher::class));
    }
}
