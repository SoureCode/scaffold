<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\EventListener;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadata;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeFlushListener;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakePersistBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeUpdateBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\InterfaceStampable;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\Stampable;

final class AbstractFlushListenerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Fixtures'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse('sqlite:///:memory:'),
            $config,
        );

        $this->entityManager = new EntityManager($connection, $config);

        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Stampable::class),
            $this->entityManager->getClassMetadata(InterfaceStampable::class),
        ]);
    }

    public function testShouldRunFalseBailsPrePersist(): void
    {
        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                persistBindings: [new FakePersistBinding(new \ReflectionProperty(Stampable::class, 'persistStamp'))],
            ),
            enabled: false,
        );

        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertNull($entity->persistStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testPrePersistStampsPersistBindingWhenPropertyNull(): void
    {
        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                persistBindings: [new FakePersistBinding(new \ReflectionProperty(Stampable::class, 'persistStamp'))],
            ),
            stampFactory: static fn(): string => 'created',
        );

        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertSame('created', $entity->persistStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testPrePersistDoesNotOverwriteExistingValue(): void
    {
        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                persistBindings: [new FakePersistBinding(new \ReflectionProperty(Stampable::class, 'persistStamp'))],
            ),
        );

        $entity = new Stampable('hello');
        $entity->persistStamp = 'preset';
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertSame('preset', $entity->persistStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testPrePersistFillsNonNullableUpdateBinding(): void
    {
        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                updateBindings: [new FakeUpdateBinding(new \ReflectionProperty(Stampable::class, 'updateStamp'), nullable: false)],
            ),
            stampFactory: static fn(): string => 'fresh',
        );

        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertSame('fresh', $entity->updateStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testPrePersistSkipsNullableUpdateBinding(): void
    {
        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                updateBindings: [new FakeUpdateBinding(new \ReflectionProperty(Stampable::class, 'updateStamp'), nullable: true)],
            ),
        );

        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertNull($entity->updateStamp);
        self::assertSame(0, $listener->resolveValueCalls);
    }

    public function testPrePersistInterfaceFallbackWhenMetadataIsEmpty(): void
    {
        $listener = $this->attachListener(metadata: new FakeBehaviorMetadataFactory());

        $entity = new InterfaceStampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertSame('persist-fallback', $entity->getInterfaceStamp());
        self::assertSame(1, $listener->persistFallbackCalls);
    }

    public function testOnFlushStampsUpdateBindingOnScheduledUpdate(): void
    {
        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                updateBindings: [new FakeUpdateBinding(new \ReflectionProperty(Stampable::class, 'updateStamp'))],
            ),
            stampFactory: static fn(): string => 'updated',
        );

        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('updated', $entity->updateStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    public function testOnFlushInterfaceFallbackOnUpdate(): void
    {
        $entity = new InterfaceStampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $listener = $this->attachListener(metadata: new FakeBehaviorMetadataFactory());

        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('update-fallback', $entity->getInterfaceStamp());
        self::assertSame(1, $listener->updateFallbackCalls);
    }

    public function testChangeBindingFiresWhenWatchedFieldChanges(): void
    {
        $entity = new Stampable('hello');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $listener = $this->attachListener(
            metadata: $this->metadataFactoryFor(
                changeBindings: [new FakeChangeBinding(
                    new \ReflectionProperty(Stampable::class, 'updateStamp'),
                    ['label'],
                )],
            ),
            stampFactory: static fn(): string => 'watcher',
        );

        $entity->label = 'changed';
        $this->entityManager->flush();

        self::assertSame('watcher', $entity->updateStamp);
        self::assertSame(1, $listener->resolveValueCalls);
    }

    /**
     * @param array<class-string, FakeBehaviorMetadata>|FakeBehaviorMetadataFactory $metadata
     */
    private function attachListener(
        FakeBehaviorMetadataFactory $metadata,
        ?callable $stampFactory = null,
        bool $enabled = true,
    ): FakeFlushListener {
        $listener = new FakeFlushListener(
            $metadata,
            new ChangeSetMatcher(),
            $stampFactory,
            $enabled,
        );

        $this->entityManager->getEventManager()->addEventListener(
            [Events::prePersist, Events::onFlush],
            $listener,
        );

        return $listener;
    }

    /**
     * @param list<\SoureCode\Component\DoctrineExtensions\Metadata\PersistBindingInterface> $persistBindings
     * @param list<\SoureCode\Component\DoctrineExtensions\Metadata\UpdateBindingInterface> $updateBindings
     * @param list<\SoureCode\Component\DoctrineExtensions\Metadata\ChangeBindingInterface> $changeBindings
     */
    private function metadataFactoryFor(
        array $persistBindings = [],
        array $updateBindings = [],
        array $changeBindings = [],
    ): FakeBehaviorMetadataFactory {
        return new FakeBehaviorMetadataFactory([
            Stampable::class => new FakeBehaviorMetadata($persistBindings, $updateBindings, $changeBindings),
        ]);
    }
}
