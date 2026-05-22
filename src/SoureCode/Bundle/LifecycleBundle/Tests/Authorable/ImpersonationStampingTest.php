<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\AuditedArticle;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\User;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedAuthorProvider;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support\FixedImpersonatorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Component\Lifecycle\Author\ImpersonatorProviderInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class ImpersonationStampingTest extends AbstractBundleTestCase
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
        $kernel->addTestBundle(SecurityBundle::class);
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(LifecycleBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/functional.php');
        $kernel->addTestConfig(__DIR__ . '/config/impersonator.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testListenerStampsImpersonatorOnPersistWhenSwitchUserActive(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(AuditedArticle::class),
        ]);

        $admin = new User('admin');
        $alice = new User('alice');
        $entityManager->persist($admin);
        $entityManager->persist($alice);
        $entityManager->flush();

        $container->get('app.author_provider')->setAuthor($alice);
        $container->get('app.impersonator_provider')->setImpersonator($admin);

        $article = new AuditedArticle('hello');
        $entityManager->persist($article);
        $entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy(), 'CreatedBy is the impersonated user');
        self::assertSame($admin, $article->getImpersonatedBy(), 'ImpersonatedBy is the original admin');
    }

    public function testListenerLeavesImpersonatedByNullWhenNoSwitchUserActive(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(AuditedArticle::class),
        ]);

        $alice = new User('alice');
        $entityManager->persist($alice);
        $entityManager->flush();

        $container->get('app.author_provider')->setAuthor($alice);
        $container->get('app.impersonator_provider')->setImpersonator(null);

        $article = new AuditedArticle('hello');
        $entityManager->persist($article);
        $entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy());
        self::assertNull($article->getImpersonatedBy());
    }

    public function testImpersonatorProviderInterfaceIsAliasedToWiredService(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(
            FixedImpersonatorProvider::class,
            $container->get(ImpersonatorProviderInterface::class),
        );
    }
}
