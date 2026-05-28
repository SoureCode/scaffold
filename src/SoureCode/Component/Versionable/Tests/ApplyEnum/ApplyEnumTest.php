<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\ApplyEnum;

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
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Badge;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Geo;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Owner;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\ProbeStatus;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Subject;
use SoureCode\Component\Versionable\Tests\VersionField\Fixtures\Tag;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Regression: `Versioner::applyVersion()` must rehydrate enum-typed columns
 * back into their PHP enum cases instead of writing the raw backing scalar
 * to a typed enum property (which would TypeError at runtime).
 */
final class ApplyEnumTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../VersionField/Fixtures'],
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
            VersionableListenerFactory::create($factory, new MockClock('2026-05-28T10:00:00+00:00')),
        );
        $this->entityManager->getEventManager()->addEventListener(
            [ToolEvents::postGenerateSchema],
            new VersionableSchemaListener($factory),
        );

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Subject::class),
            $this->entityManager->getClassMetadata(Owner::class),
            $this->entityManager->getClassMetadata(Badge::class),
            $this->entityManager->getClassMetadata(Tag::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testApplyVersionRestoresEnumValueAsEnumInstance(): void
    {
        $subject = new Subject('hello', new Geo(1.0, 2.0));
        $this->entityManager->persist($subject);
        $this->entityManager->flush();
        // v=1: status = Draft (insert default)

        $subject->setStatus(ProbeStatus::Published);
        $this->entityManager->flush();
        // v=2: status = Published

        $this->versioner->applyVersion($subject, 1);

        $statusProperty = new \ReflectionProperty(Subject::class, 'status');

        self::assertInstanceOf(ProbeStatus::class, $statusProperty->getValue($subject), 'enum property is restored as the enum case, not the backing string');
        self::assertSame(ProbeStatus::Draft, $statusProperty->getValue($subject));
    }
}
