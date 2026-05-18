<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\TraceableBundle\EventListener\ConsoleTraceListener;
use SoureCode\Bundle\TraceableBundle\EventListener\HttpTraceListener;
use SoureCode\Bundle\TraceableBundle\Messenger\TraceContextMiddleware;
use SoureCode\Bundle\TraceableBundle\TraceableBundle;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextInterface;
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
        $kernel->addTestBundle(TraceableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/framework.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(TraceContextFactory::class));
        self::assertTrue($container->has(TraceContext::class));
        self::assertTrue($container->has(TraceContextInterface::class));
        self::assertTrue($container->has(HttpTraceListener::class));
        self::assertTrue($container->has(ConsoleTraceListener::class));
        self::assertTrue($container->has(TraceContextMiddleware::class));
    }
}
