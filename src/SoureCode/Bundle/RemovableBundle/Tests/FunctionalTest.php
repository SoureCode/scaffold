<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\AuthorableBundle\AuthorableBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\RemovableBundle\RemovableBundle;
use SoureCode\Bundle\RemovableBundle\Tests\Fixtures\Note;
use SoureCode\Bundle\TimestampableBundle\TimestampableBundle;
use SoureCode\Component\Removable\Remover;
use SoureCode\Component\Removable\RemoverInterface;
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
        $kernel->addTestBundle(TimestampableBundle::class);
        $kernel->addTestBundle(AuthorableBundle::class);
        $kernel->addTestBundle(RemovableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/functional.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testWiredRemoverSoftDeletesAndRestoresThroughContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $remover = $container->get(RemoverInterface::class);

        $note = new Note('hello');
        $entityManager->persist($note);
        $entityManager->flush();

        $remover->remove($note);
        self::assertNotNull($note->getDeletedAt(), 'remove() sets deletedAt');

        $remover->restore($note);
        self::assertNull($note->getDeletedAt(), 'restore() clears deletedAt');
    }

    public function testRemoverInterfaceAliasResolvesToConcreteRemover(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $byInterface = $container->get(RemoverInterface::class);
        $byClass = $container->get(Remover::class);

        self::assertSame($byClass, $byInterface);
    }
}
