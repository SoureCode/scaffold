<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\SelfRef;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\SelfRef\Fixtures\Branch;
use SoureCode\Component\Versionable\Tests\SelfRef\Fixtures\TreeNode;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use Symfony\Component\Clock\MockClock;

/**
 * #38 — a self-referential `ManyToOne`. The endpoint that gets a new parent
 * always bumps; the parent bumps only when the relation is bidirectional.
 */
final class SelfRefTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        $factory = new VersionableMetadataFactory();
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-26T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(TreeNode::class),
            $this->entityManager->getClassMetadata(Branch::class),
        ]);
    }

    public function testBidirectionalSelfRefBumpsBothEndpoints(): void
    {
        $root = new TreeNode('root');
        $child = new TreeNode('child');
        $this->entityManager->persist($root);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $child->setParent($root);
        $this->entityManager->flush();

        self::assertSame(1, $child->getVersion(), 'child (owning self-ref) bumps');
        self::assertSame(1, $root->getVersion(), 'parent bumps — bidirectional self-ref');
    }

    public function testUnidirectionalSelfRefBumpsOnlySelf(): void
    {
        $root = new Branch('root');
        $child = new Branch('child');
        $this->entityManager->persist($root);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $child->setParent($root);
        $this->entityManager->flush();

        self::assertSame(1, $child->getVersion(), 'child bumps');
        self::assertSame(0, $root->getVersion(), 'parent untouched — unidirectional self-ref stays one-sided');
    }
}
