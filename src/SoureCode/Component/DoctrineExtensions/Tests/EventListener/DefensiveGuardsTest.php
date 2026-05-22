<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeFlushListener;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\Sample;

/**
 * Covers defensive branches that the public Doctrine flow never exercises
 * (collections that hold a non-object element; PersistentCollection with
 * a null owner). The base listener and the change-set matcher guard
 * against those situations to keep the toolkit usable with custom Collection
 * implementations that do not enforce object-ness, and we want the guards
 * to stay honest under refactor.
 *
 * Tests reach into the private walkPath / matchesPath / touchCollectionWatchers
 * methods through reflection because the guards trip pre-Doctrine: a
 * Doctrine integration test cannot inject the malformed collection that
 * the guards exist for.
 */
final class DefensiveGuardsTest extends TestCase
{
    public function testWalkPathSkipsNonObjectCollectionElementsAndStillFindsTrailingMatch(): void
    {
        $listener = new FakeFlushListener(new FakeBehaviorMetadataFactory(), new ChangeSetMatcher());

        $current = new Sample('current');
        $match = new Sample('match');
        // The scalar "not-an-object" must be silently skipped; the walker
        // must continue iterating and find the trailing object that IS
        // $changedRelated. If the guard ever flipped from `continue` to
        // `return false`, the walk would miss the match.
        $current->children = new ArrayCollection(['not-an-object', $match]);

        $nested = null;
        $visited = new \SplObjectStorage();

        $walkPath = new \ReflectionMethod($listener, 'walkPath');
        $args = [$current, 'children.label', $match, &$nested, $visited];
        $result = $walkPath->invokeArgs($listener, $args);

        self::assertTrue($result, 'walker must keep iterating after a non-object element and reach the trailing match');
        self::assertSame('label', $nested);
    }

    public function testMatchesPathSkipsNonObjectCollectionElementsAndStillReachesTrailingMatch(): void
    {
        $matcher = new ChangeSetMatcher();

        $entity = new Sample('parent');
        $valid = new Sample('valid');
        // First element is a scalar — must be skipped. The second element
        // has a `label` change in the unit of work's change set so the
        // matcher's collection branch must keep walking past the scalar
        // and report a hit on the real entity.
        $entity->children = new ArrayCollection([123, $valid]);

        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['children.label'],
        );

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')
            ->willReturnCallback(static fn (object $passed): array => $passed === $valid ? ['label' => ['old', 'new']] : []);

        self::assertTrue($matcher->matches($binding, $entity, $unitOfWork));
    }

    public function testTouchCollectionWatchersBailsOnNullOwner(): void
    {
        $listener = new FakeFlushListener(
            new FakeBehaviorMetadataFactory(),
            new ChangeSetMatcher(),
            static fn (): string => 'should-not-fire',
        );

        $collection = $this->createPersistentCollectionWithoutOwner();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $unitOfWork = $this->createStub(UnitOfWork::class);

        // Reach into the private method directly: the no-owner case is
        // unreachable through Doctrine's own collection lifecycle, but
        // the guard is the only thing keeping the listener from NPE'ing
        // on `$owner::class` further down. Removing the guard turns this
        // test into a TypeError.
        $method = new \ReflectionMethod($listener, 'touchCollectionWatchers');
        $method->invoke($listener, $collection, $entityManager, $unitOfWork, new \SplObjectStorage());

        self::assertSame(0, $listener->resolveValueCalls);
    }

    private function createPersistentCollectionWithoutOwner(): PersistentCollection
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $classMetadata = $this->createStub(ClassMetadata::class);

        $reflection = new \ReflectionClass(PersistentCollection::class);
        /** @var PersistentCollection $collection */
        $collection = $reflection->newInstanceWithoutConstructor();

        $initCollection = $reflection->getProperty('collection');
        $initCollection->setValue($collection, new ArrayCollection());

        $em = $reflection->getProperty('em');
        $em->setValue($collection, $entityManager);

        $typeClass = $reflection->getProperty('typeClass');
        $typeClass->setValue($collection, $classMetadata);

        return $collection;
    }
}
