<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadata;
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
    public function testWalkPathSkipsNonObjectCollectionElements(): void
    {
        $listener = new FakeFlushListener(new FakeBehaviorMetadataFactory(), new ChangeSetMatcher());

        $current = new Sample('current');
        // ArrayCollection's contract is "Collection of mixed", so a scalar
        // entry is legal at the type system level — the walker's guard
        // is the only thing keeping the loop from passing a string to a
        // recursive walkPath call.
        $current->children = new ArrayCollection(['not-an-object', new Sample('real')]);

        $changedRelated = new Sample('changed');
        $nested = null;
        $visited = new \SplObjectStorage();

        $walkPath = new \ReflectionMethod($listener, 'walkPath');
        $args = [$current, 'children.label', $changedRelated, &$nested, $visited];
        $result = $walkPath->invokeArgs($listener, $args);

        // The string is silently skipped and the real Sample is walked;
        // neither matches $changedRelated, so the walk returns false.
        self::assertFalse($result);
    }

    public function testMatchesPathSkipsNonObjectCollectionElements(): void
    {
        $matcher = new ChangeSetMatcher();

        $entity = new Sample('parent');
        $entity->children = new ArrayCollection([123, new Sample('valid')]);

        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['children.label'],
        );

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn([]);

        // No element matches, but the integer is skipped without throwing
        // and the walk completes returning false.
        self::assertFalse($matcher->matches($binding, $entity, $unitOfWork));
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
        // on `$owner::class` further down.
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

        // Initialize internal Collection state without setting an owner.
        $initCollection = $reflection->getProperty('collection');
        $initCollection->setValue($collection, new ArrayCollection());

        $em = $reflection->getProperty('em');
        $em->setValue($collection, $entityManager);

        $typeClass = $reflection->getProperty('typeClass');
        $typeClass->setValue($collection, $classMetadata);

        return $collection;
    }
}
