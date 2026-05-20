<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RecentAuthenticationBundle\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\AccessDeniedListener;
use SoureCode\Bundle\RecentAuthenticationBundle\EventListener\LoginSuccessListener;
use SoureCode\Bundle\RecentAuthenticationBundle\RecentAuthenticationBundle;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\RecentAuthentication;
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use SoureCode\Bundle\RecentAuthenticationBundle\Twig\RecentAuthenticationExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
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
        $kernel->addTestBundle(SecurityBundle::class);
        $kernel->addTestBundle(TwigBundle::class);
        $kernel->addTestBundle(RecentAuthenticationBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/bundle.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testServicesAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(RecentAuthentication::class));
        self::assertTrue($container->has(RecentAuthenticationVoter::class));
        self::assertTrue($container->has(LoginSuccessListener::class));
        self::assertTrue($container->has(AccessDeniedListener::class));
        self::assertTrue($container->has(RecentAuthenticationExtension::class));
    }

    public function testTtlConfigPropagatesToService(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(__DIR__ . '/config/custom_ttl.php');
        }]);

        $container = self::getContainer();
        $service = $container->get(RecentAuthentication::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('ttlSeconds');

        self::assertSame(60, $property->getValue($service));
    }

    public function testLoginRouteConfigPropagatesToListener(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(__DIR__ . '/config/custom_login_route.php');
        }]);

        $container = self::getContainer();
        $listener = $container->get(AccessDeniedListener::class);

        $reflection = new \ReflectionClass($listener);
        $property = $reflection->getProperty('loginRoute');

        self::assertSame('my_login', $property->getValue($listener));
    }
}
