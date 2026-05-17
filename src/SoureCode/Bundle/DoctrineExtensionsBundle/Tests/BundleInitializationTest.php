<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/framework.php');
        $kernel->addTestCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition(ChangeSetMatcher::class)) {
                    $container->getDefinition(ChangeSetMatcher::class)->setPublic(true);
                }
            }
        });
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testChangeSetMatcherIsRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(ChangeSetMatcher::class));
        self::assertInstanceOf(ChangeSetMatcher::class, $container->get(ChangeSetMatcher::class));
    }
}
