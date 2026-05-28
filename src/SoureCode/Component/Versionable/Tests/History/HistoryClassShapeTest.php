<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\History;

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
use SoureCode\Component\Versionable\Tests\Embeddable\Fixtures\CustomerLocation;
use SoureCode\Component\Versionable\Tests\Embeddable\Fixtures\PostalAddress;
use SoureCode\Component\Versionable\Tests\Fixtures\Article;
use SoureCode\Component\Versionable\Tests\VersionableListenerFactory;
use SoureCode\Component\Versionable\Versioner;
use Symfony\Component\Clock\MockClock;

/**
 * Phase 3 — exercises the generated `*History` classes directly:
 *   - scalar/embedded getters exist and return the right types,
 *   - the class is final and has no setters,
 *   - associations are NOT exposed (phase 5 territory),
 *   - hydration matches the snapshot row.
 */
final class HistoryClassShapeTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Versioner $versioner;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__ . '/../Fixtures',
                __DIR__ . '/../Embeddable/Fixtures',
            ],
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
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(CustomerLocation::class),
        ]);

        $this->versioner = new Versioner($this->entityManager, $factory);
    }

    public function testHistoryClassExposesScalarGettersOnly(): void
    {
        $article = new Article('hello');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $history = $this->versioner->findByVersion(Article::class, $article->getId(), 1);
        $historyClass = Versioner::historyClassFor(Article::class);

        self::assertNotNull($history);
        self::assertInstanceOf($historyClass, $history);

        $reflection = new \ReflectionClass($historyClass);
        self::assertTrue($reflection->isFinal(), 'generated *History class is final');

        $methodNames = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $reflection->getMethods());

        self::assertContains('getId', $methodNames);
        self::assertContains('getVersion', $methodNames);
        self::assertContains('getTitle', $methodNames);
        self::assertContains('getBody', $methodNames);
        self::assertContains('getInternalNote', $methodNames);

        foreach ($methodNames as $name) {
            self::assertStringStartsNotWith('set', $name, 'no setters on history class');
        }
    }

    public function testHistoryHydratesScalarValuesFromSnapshot(): void
    {
        $article = new Article('hello');
        $article->setBody('body-1');
        $article->setInternalNote('note-1');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $history = $this->versioner->findByVersion(Article::class, $article->getId(), 1);

        self::assertNotNull($history);
        self::assertSame($article->getId(), $history->getId());
        self::assertSame(1, $history->getVersion());
        self::assertSame('hello', $history->getTitle());
        self::assertSame('body-1', $history->getBody());
        self::assertSame('note-1', $history->getInternalNote());
    }

    public function testHistoryHydratesEmbeddedValueObject(): void
    {
        $location = new CustomerLocation('HQ', new PostalAddress('Main 1', 'Berlin'));
        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $history = $this->versioner->findByVersion(CustomerLocation::class, $location->getId(), 1);

        self::assertNotNull($history);

        $historyClass = Versioner::historyClassFor(CustomerLocation::class);
        $reflection = new \ReflectionClass($historyClass);

        self::assertTrue($reflection->hasMethod('getAddress'));

        $address = $history->getAddress();
        self::assertInstanceOf(PostalAddress::class, $address);
        self::assertSame('Main 1', $address->getStreet());
        self::assertSame('Berlin', $address->getCity());
    }

    public function testHistoryHasNoAssociationGetters(): void
    {
        // Phase 3 generates History DTOs with scalar/embedded getters only;
        // association getters are phase-5 work and must NOT exist here.
        $historyClass = Versioner::historyClassFor(Article::class);
        $this->versioner->findByVersion(Article::class, 0, 0); // ensure class is generated

        $reflection = new \ReflectionClass($historyClass);
        $methods = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $reflection->getMethods());

        // Article has no associations so this is trivially true, but the
        // shape contract is: phase 3 emits only own-row getters, never any
        // method like getXxxHistory or get<Relation>.
        self::assertContains('getId', $methods);
        self::assertSame(
            $methods,
            array_filter($methods, static fn (string $m): bool => !str_ends_with($m, 'History') && !str_starts_with($m, 'set')),
        );
    }

    public function testHistoryConstructorPropertiesAreReadonly(): void
    {
        $historyClass = Versioner::historyClassFor(Article::class);
        $this->versioner->findByVersion(Article::class, 0, 0); // ensure class is generated

        $reflection = new \ReflectionClass($historyClass);

        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName() . ' is readonly');
        }
    }
}
