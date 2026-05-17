<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\AuthorableBundle\AuthorableBundle;
use SoureCode\Bundle\AuthorableBundle\Security\SecurityAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Component\Authorable\Author\AuthorProviderInterface;
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
        $kernel->addTestBundle(AuthorableBundle::class);
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
