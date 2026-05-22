<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Removable;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\LifecycleBundle\LifecycleBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\LifecycleBundle\Tests\Removable\Fixtures\Note;
use SoureCode\Component\Lifecycle\Remover;
use SoureCode\Component\Lifecycle\RemoverInterface;
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
        $kernel->addTestBundle(LifecycleBundle::class);
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

    public function testBatchRemoveSoftDeletesEveryEntity(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $first = new Note('a');
        $second = new Note('b');
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        $remover = $container->get(RemoverInterface::class);
        $count = $remover->batchRemove([$first, $second]);

        self::assertSame(2, $count);
        self::assertNotNull($first->getDeletedAt());
        self::assertNotNull($second->getDeletedAt());
    }

    public function testPurgeHardDeletesSoftDeletedRowsOlderThanCutoff(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $remover = $container->get(RemoverInterface::class);

        $old = new Note('old');
        $entityManager->persist($old);
        $entityManager->flush();
        $remover->remove($old);
        $old->setDeletedAt(new \DateTimeImmutable('2020-01-01 00:00:00'));
        $entityManager->flush();

        $young = new Note('young');
        $entityManager->persist($young);
        $entityManager->flush();
        $remover->remove($young);

        $purged = $remover->purge(Note::class, new \DateTimeImmutable('2024-01-01 00:00:00'));

        self::assertSame(1, $purged);
        $entityManager->clear();

        $repo = $entityManager->getRepository(Note::class);
        self::assertNull($repo->find($old->id));
        self::assertNotNull($repo->find($young->id));
    }
}
