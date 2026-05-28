<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\UlidIdentifier;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\EventListener\VersionableSchemaListener;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use SoureCode\Component\Versionable\Tests\UlidIdentifier\Fixtures\UlidEntity;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

/**
 * Exercises the full Versionable lifecycle on an entity keyed by a Symfony
 * `Ulid` — proves that the read/apply/pin paths handle non-scalar
 * identifiers via DBAL Type conversion at every bind site.
 */
final class UlidIdentifierTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

    public static function setUpBeforeClass(): void
    {
        if (!Type::hasType(UlidType::NAME)) {
            Type::addType(UlidType::NAME, UlidType::class);
        }
    }

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

        $factory = new VersionableMetadataFactory($this->entityManager);
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush],
            VersionableListenerFactory::create($factory, new MockClock('2026-05-28T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(UlidEntity::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testInsertSeedsV1SnapshotForUlidKeyedEntity(): void
    {
        $entity = new UlidEntity('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertSame(1, $entity->getVersion());

        $history = $this->versioner->findHistory(UlidEntity::class, $entity->getId());

        self::assertCount(1, $history);
        self::assertSame('hello', $history[0]->getTitle());
    }

    public function testFindByVersionRoundtripsUlidThroughReader(): void
    {
        $entity = new UlidEntity('alpha');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('beta');
        $this->entityManager->flush();

        $v1 = $this->versioner->findByVersion(UlidEntity::class, $entity->getId(), 1);
        $v2 = $this->versioner->findByVersion(UlidEntity::class, $entity->getId(), 2);

        self::assertNotNull($v1);
        self::assertNotNull($v2);
        self::assertInstanceOf(Ulid::class, $v1->getId());
        self::assertTrue($entity->getId()->equals($v1->getId()), 'Ulid round-tripped through the snapshot');
        self::assertSame('alpha', $v1->getTitle());
        self::assertSame('beta', $v2->getTitle());
    }

    public function testApplyVersionRestoresEntityWithUlidIdentifier(): void
    {
        $entity = new UlidEntity('one');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('two');
        $this->entityManager->flush();

        $entity->setTitle('three');
        $this->entityManager->flush();

        self::assertSame(3, $entity->getVersion());

        $this->versioner->applyVersion($entity, 2);

        self::assertSame('two', $entity->getTitle(), 'apply works for a Ulid-keyed entity');
    }

    public function testDiffWorksForUlidIdentifier(): void
    {
        $entity = new UlidEntity('a');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setTitle('b');
        $this->entityManager->flush();

        $diff = $this->versioner->diff(UlidEntity::class, $entity->getId(), 1, 2);

        self::assertNotNull($diff);
        self::assertTrue($diff->hasChanges());
        self::assertSame('a', $diff->changes['title']['before']);
        self::assertSame('b', $diff->changes['title']['after']);
    }
}
