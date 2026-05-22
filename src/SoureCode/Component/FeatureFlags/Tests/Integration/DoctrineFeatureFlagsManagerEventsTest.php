<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\FeatureFlags\Doctrine\FeatureFlagMappingDriver;
use SoureCode\Component\FeatureFlags\Event\FeatureFlagRemovedEvent;
use SoureCode\Component\FeatureFlags\Event\FeatureFlagToggledEvent;
use SoureCode\Component\FeatureFlags\Factory\FeatureFlagFactory;
use SoureCode\Component\FeatureFlags\Manager\DoctrineFeatureFlagsManager;
use SoureCode\Component\FeatureFlags\Model\FeatureFlag;
use SoureCode\Component\FeatureFlags\Tests\Support\RecordingEventDispatcher;

final class DoctrineFeatureFlagsManagerEventsTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RecordingEventDispatcher $dispatcher;
    private DoctrineFeatureFlagsManager $manager;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->setMetadataDriverImpl(new FeatureFlagMappingDriver());
        $config->enableNativeLazyObjects(true);

        $dsnParser = new DsnParser(['sqlite' => 'pdo_sqlite']);
        $connection = DriverManager::getConnection(
            $dsnParser->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(FeatureFlag::class),
        ]);

        $this->dispatcher = new RecordingEventDispatcher();
        $this->manager = new DoctrineFeatureFlagsManager(
            entityManager: $this->entityManager,
            featureFlagEntityClassName: FeatureFlag::class,
            featureFlagFactory: new FeatureFlagFactory(),
            eventDispatcher: $this->dispatcher,
        );
    }

    public function testEnableOnMissingFlagDispatchesCreatedToggleEvent(): void
    {
        $this->manager->enable('beta');

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(FeatureFlagToggledEvent::class, $event);
        self::assertSame('beta', $event->name);
        self::assertTrue($event->enabled);
        self::assertTrue($event->created);
    }

    public function testDisableOnExistingFlagDispatchesToggleEventWithCreatedFalse(): void
    {
        $this->manager->enable('beta');
        $this->dispatcher->events = [];

        $this->manager->disable('beta');

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(FeatureFlagToggledEvent::class, $event);
        self::assertSame('beta', $event->name);
        self::assertFalse($event->enabled);
        self::assertFalse($event->created);
    }

    public function testRemoveDispatchesRemovedEvent(): void
    {
        $this->manager->enable('beta');
        $this->dispatcher->events = [];

        $this->manager->remove('beta');

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(FeatureFlagRemovedEvent::class, $event);
        self::assertSame('beta', $event->name);
    }

    public function testRemoveOfUnknownFlagDoesNotDispatch(): void
    {
        $this->manager->remove('does-not-exist');

        self::assertSame([], $this->dispatcher->events);
    }
}
