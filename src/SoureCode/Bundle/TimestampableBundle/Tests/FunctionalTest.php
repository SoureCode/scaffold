<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\TimestampableBundle\Tests\Fixtures\Note;
use SoureCode\Bundle\TimestampableBundle\TimestampableBundle;
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
        $kernel->addTestBundle(DoctrineExtensionsBundle::class);
        $kernel->addTestBundle(TimestampableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/doctrine.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testPersistAndUpdateStampTimestampsThroughBundleListeners(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $note = new Note('first');
        $entityManager->persist($note);
        $entityManager->flush();

        self::assertNotNull($note->getCreatedAt(), 'prePersist listener must stamp createdAt');
        self::assertNull($note->getUpdatedAt(), 'updatedAt is nullable on persist');
        self::assertNull($note->getDeletedAt());

        $note->body = 'changed';
        $entityManager->flush();

        self::assertNotNull($note->getUpdatedAt(), 'onFlush listener must stamp updatedAt on update');
    }
}
