<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\DoctrineExtensionsBundle\DoctrineExtensionsBundle;
use SoureCode\Bundle\DoctrineExtensionsBundle\Testing\AbstractBundleTestCase;
use SoureCode\Bundle\VersionableBundle\Tests\Fixtures\Page;
use SoureCode\Bundle\VersionableBundle\VersionableBundle;
use SoureCode\Component\Versionable\Versioner;
use SoureCode\Component\Versionable\VersionerInterface;
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
        $kernel->addTestBundle(VersionableBundle::class);
        $kernel->addTestConfig(__DIR__ . '/config/functional.php');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testListenersFireAndProduceSnapshotsThroughBundleDi(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Page::class),
        ]);

        $page = new Page('hello');
        $entityManager->persist($page);
        $entityManager->flush();

        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_bundle_page_version WHERE entity_id = ?',
            [$page->id],
        );
        self::assertCount(0, $rows, 'No snapshot on insert');

        $page->title = 'updated';
        $entityManager->flush();

        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM versionable_bundle_page_version WHERE entity_id = ? ORDER BY version ASC',
            [$page->id],
        );
        self::assertCount(1, $rows, 'Update produces exactly one snapshot row');
        self::assertSame('updated', $rows[0]['title']);
    }

    public function testVersionerInterfaceAliasResolvesToConcreteVersioner(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $byInterface = $container->get(VersionerInterface::class);
        $byClass = $container->get(Versioner::class);

        self::assertSame($byClass, $byInterface);
    }
}
