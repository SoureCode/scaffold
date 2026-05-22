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
use SoureCode\Component\Lifecycle\Doctrine\SoftDeleteFilter;
use SoureCode\Component\Lifecycle\RemoverInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\KernelInterface;

final class SoftDeleteFilterTest extends AbstractBundleTestCase
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
        $kernel->addTestConfig(__DIR__ . '/config/soft_delete_filter.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testFilterHidesSoftDeletedRowsFromFindAll(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $alive = new Note('alive');
        $doomed = new Note('doomed');
        $entityManager->persist($alive);
        $entityManager->persist($doomed);
        $entityManager->flush();

        $container->get(RemoverInterface::class)->remove($doomed);
        $entityManager->clear();

        $rows = $entityManager->getRepository(Note::class)->findAll();

        self::assertCount(1, $rows);
        self::assertSame('alive', $rows[0]->body);
    }

    public function testFilterCanBeDisabledPerRequest(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Note::class),
        ]);

        $note = new Note('disabled-filter-target');
        $entityManager->persist($note);
        $entityManager->flush();
        $container->get(RemoverInterface::class)->remove($note);
        $entityManager->clear();

        $entityManager->getFilters()->disable('soft_delete');

        $rows = $entityManager->getRepository(Note::class)->findAll();
        self::assertCount(1, $rows);
    }

    public function testFilterClassIsRegistered(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $filter = $entityManager->getFilters()->getFilter('soft_delete');
        self::assertInstanceOf(SoftDeleteFilter::class, $filter);
    }
}
