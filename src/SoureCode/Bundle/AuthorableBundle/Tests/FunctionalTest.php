<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\AuthorableBundle\AuthorableBundle;
use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\Article;
use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\User;
use SoureCode\Bundle\AuthorableBundle\Tests\Support\FixedAuthorProvider;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class FunctionalTest extends AbstractBundleTestCase
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
        $kernel->addTestBundle(AuthorableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/functional.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testPersistAndUpdateStampAuthorsThroughBundleListeners(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(Article::class),
        ]);

        $alice = new User('alice');
        $bob = new User('bob');
        $entityManager->persist($alice);
        $entityManager->persist($bob);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);

        $provider->setAuthor($alice);

        $article = new Article('hello');
        $entityManager->persist($article);
        $entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy(), 'prePersist must stamp createdBy via listener');
        self::assertNull($article->getUpdatedBy(), 'updatedBy is nullable (default) and stays null on persist');

        $provider->setAuthor($bob);

        $article->title = 'edited';
        $entityManager->flush();

        self::assertSame($alice, $article->getCreatedBy(), 'createdBy is never overwritten');
        self::assertSame($bob, $article->getUpdatedBy(), 'onFlush listener must update updatedBy to the active author');
    }

    public function testListenerPreservesUserSetUpdatedBy(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(Article::class),
        ]);

        $alice = new User('alice');
        $bob = new User('bob');
        $carol = new User('carol');
        $entityManager->persist($alice);
        $entityManager->persist($bob);
        $entityManager->persist($carol);
        $entityManager->flush();

        $provider = $container->get('app.author_provider');
        self::assertInstanceOf(FixedAuthorProvider::class, $provider);

        $provider->setAuthor($alice);
        $article = new Article('hello');
        $entityManager->persist($article);
        $entityManager->flush();

        $provider->setAuthor($bob);
        $article->title = 'edited';
        $article->setUpdatedBy($carol);
        $entityManager->flush();

        self::assertSame(
            $carol,
            $article->getUpdatedBy(),
            'listener must NOT overwrite an UpdatedBy value the user set explicitly',
        );
    }

    public function testUserClassConfigPropagatesToMappingListener(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $classMetadata = $entityManager->getClassMetadata(Article::class);
        $association = $classMetadata->getAssociationMapping('createdBy');

        self::assertSame(User::class, $association->targetEntity);
    }
}
