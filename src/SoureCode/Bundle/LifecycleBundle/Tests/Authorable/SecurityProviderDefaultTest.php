<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\LifecycleBundle\Security\SecurityAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class SecurityProviderDefaultTest extends AbstractBundleTestCase
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
        $kernel->addTestBundle(SecurityBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/security.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testSecurityAuthorProviderIsAliasedByDefault(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(AuthorProviderInterface::class));
        self::assertInstanceOf(SecurityAuthorProvider::class, $container->get(AuthorProviderInterface::class));
    }
}
