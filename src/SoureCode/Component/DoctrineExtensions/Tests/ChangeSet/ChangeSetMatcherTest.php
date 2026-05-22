<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\ChangeSet;

use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\Sample;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\SampleStatus;

final class ChangeSetMatcherTest extends TestCase
{
    public function testFiresWhenAnyWatchedFieldInChangeset(): void
    {
        $matcher = new ChangeSetMatcher();
        $sample = new Sample();
        $binding = $this->makeBinding($sample, ['label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($sample) => ['label' => ['old', 'new']],
        ]);

        self::assertTrue($matcher->matches($binding, $sample, $unitOfWork));
    }

    public function testIgnoresUnrelatedChange(): void
    {
        $matcher = new ChangeSetMatcher();
        $sample = new Sample();
        $binding = $this->makeBinding($sample, ['label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($sample) => ['other' => ['a', 'b']],
        ]);

        self::assertFalse($matcher->matches($binding, $sample, $unitOfWork));
    }

    public function testValueMatcherEnforcesEquality(): void
    {
        $matcher = new ChangeSetMatcher();
        $sample = new Sample();
        $binding = $this->makeBinding($sample, ['label'], matchValue: true, value: 'target');
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($sample) => ['label' => ['old', 'target']],
        ]);

        self::assertTrue($matcher->matches($binding, $sample, $unitOfWork));
    }

    public function testNullValueMatcherFiresOnClearedField(): void
    {
        $matcher = new ChangeSetMatcher();
        $sample = new Sample();
        $binding = $this->makeBinding($sample, ['label'], matchValue: true, value: null);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($sample) => ['label' => ['x', null]],
        ]);

        self::assertTrue($matcher->matches($binding, $sample, $unitOfWork));
    }

    public function testDottedPathTraversesRelation(): void
    {
        $matcher = new ChangeSetMatcher();
        $parent = new Sample('p');
        $child = new Sample('c');
        $child->parent = $parent;
        $binding = $this->makeBinding($child, ['parent.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($parent) => ['label' => ['p', 'p2']],
            spl_object_id($child) => [],
        ]);

        self::assertTrue($matcher->matches($binding, $child, $unitOfWork));
    }

    public function testCycleProtectionPreventsInfiniteRecursion(): void
    {
        $matcher = new ChangeSetMatcher();
        $a = new Sample('a');
        $b = new Sample('b');
        $a->parent = $b;
        $b->parent = $a;
        $binding = $this->makeBinding($a, ['parent.parent.parent.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($a) => [],
            spl_object_id($b) => [],
        ]);

        self::assertFalse($matcher->matches($binding, $a, $unitOfWork));
    }

    /**
     * @param list<string> $fields
     */
    private function makeBinding(object $entity, array $fields, bool $matchValue = false, mixed $value = null): FakeChangeBinding
    {
        return new FakeChangeBinding(
            new \ReflectionProperty($entity::class, 'label'),
            $fields,
            $matchValue,
            $value,
        );
    }

    public function testFindPropertyWalksParentClassChain(): void
    {
        $matcher = new ChangeSetMatcher();
        $child = new class('child') extends Sample {
        };

        $property = $matcher->findProperty($child::class, 'label');

        self::assertNotNull($property);
        self::assertSame('label', $property->getName());
    }

    public function testFindPropertyReturnsNullForUnknownField(): void
    {
        $matcher = new ChangeSetMatcher();

        self::assertNull($matcher->findProperty(Sample::class, 'nonexistent'));
    }

    public function testValueMatchesReturnsTrueWhenBindingHasNoValueMatcher(): void
    {
        $matcher = new ChangeSetMatcher();
        $binding = new FakeChangeBinding(new \ReflectionProperty(Sample::class, 'label'), ['label']);

        self::assertTrue($matcher->valueMatches($binding, 'anything'));
        self::assertTrue($matcher->valueMatches($binding, null));
    }

    public function testValueMatchesEnforcesEqualityWhenBindingHasValueMatcher(): void
    {
        $matcher = new ChangeSetMatcher();
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['label'],
            matchValue: true,
            value: 'target',
        );

        self::assertTrue($matcher->valueMatches($binding, 'target'));
        self::assertFalse($matcher->valueMatches($binding, 'other'));
    }

    public function testEnumActualMatchesScalarExpected(): void
    {
        $matcher = new ChangeSetMatcher();
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['label'],
            matchValue: true,
            value: 'draft',
        );

        self::assertTrue($matcher->valueMatches($binding, SampleStatus::Draft));
        self::assertFalse($matcher->valueMatches($binding, SampleStatus::Published));
    }

    public function testScalarActualMatchesEnumExpected(): void
    {
        $matcher = new ChangeSetMatcher();
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['label'],
            matchValue: true,
            value: SampleStatus::Draft,
        );

        self::assertTrue($matcher->valueMatches($binding, 'draft'));
        self::assertFalse($matcher->valueMatches($binding, 'published'));
    }

    public function testEnumActualMatchesEnumExpectedByIdentity(): void
    {
        $matcher = new ChangeSetMatcher();
        $binding = new FakeChangeBinding(
            new \ReflectionProperty(Sample::class, 'label'),
            ['label'],
            matchValue: true,
            value: SampleStatus::Draft,
        );

        self::assertTrue($matcher->valueMatches($binding, SampleStatus::Draft));
        self::assertFalse($matcher->valueMatches($binding, SampleStatus::Published));
    }

    public function testMatchesPathDescendsIntoCollectionElement(): void
    {
        $matcher = new ChangeSetMatcher();
        $parent = new Sample('p');
        $childA = new Sample('a');
        $childB = new Sample('b');
        $parent->children->add($childA);
        $parent->children->add($childB);

        $binding = $this->makeBinding($parent, ['children.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($parent) => [],
            spl_object_id($childA) => [],
            spl_object_id($childB) => ['label' => ['b', 'b2']],
        ]);

        self::assertTrue($matcher->matches($binding, $parent, $unitOfWork));
    }

    public function testCollectionTraversalReturnsFalseWhenNoElementMatches(): void
    {
        $matcher = new ChangeSetMatcher();
        $parent = new Sample('p');
        $parent->children->add(new Sample('a'));
        $parent->children->add(new Sample('b'));

        $binding = $this->makeBinding($parent, ['children.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($parent) => [],
        ]);

        self::assertFalse($matcher->matches($binding, $parent, $unitOfWork));
    }

    public function testFiresOnNewlyAssignedRelatedFromChangeSet(): void
    {
        $matcher = new ChangeSetMatcher();
        $entity = new Sample('child');
        $oldParent = new Sample('p-old');
        $newParent = new Sample('p-new');
        $entity->parent = $newParent;

        $binding = $this->makeBinding($entity, ['parent.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($entity) => ['parent' => [$oldParent, $newParent]],
        ]);

        self::assertTrue($matcher->matches($binding, $entity, $unitOfWork));
    }

    public function testNewlyAssignedRelatedRejectsWhenTailPropertyDoesNotExist(): void
    {
        $matcher = new ChangeSetMatcher();
        $entity = new Sample('child');
        $newParent = new Sample('p-new');
        $entity->parent = $newParent;

        // Tail of the binding ("nonexistent") names a property that does
        // not exist on the new related object. matchesNewlyAssignedRelated
        // bails out via the findProperty===null branch.
        $binding = $this->makeBinding($entity, ['parent.nonexistent']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($entity) => ['parent' => [null, $newParent]],
        ]);

        self::assertFalse($matcher->matches($binding, $entity, $unitOfWork));
    }

    public function testNewlyAssignedRelatedRecursesWhenTailStillContainsDots(): void
    {
        $matcher = new ChangeSetMatcher();
        $entity = new Sample('child');
        $newGrandparent = new Sample('gp');
        $newParent = new Sample('p-new');
        $newParent->parent = $newGrandparent;
        $entity->parent = $newParent;

        // Tail is "parent.label" — still dotted, so matchesNewlyAssignedRelated
        // recurses through matchesPath instead of doing the leaf check.
        $binding = $this->makeBinding($entity, ['parent.parent.label']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($entity) => ['parent' => [null, $newParent]],
            spl_object_id($newGrandparent) => ['label' => ['old', 'gp']],
        ]);

        self::assertTrue($matcher->matches($binding, $entity, $unitOfWork));
    }

    /**
     * @param array<int, array<string, array{0: mixed, 1: mixed}>> $changeSets
     */
    private function mockUnitOfWork(array $changeSets): UnitOfWork
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')
            ->willReturnCallback(static fn(object $entity): array => $changeSets[spl_object_id($entity)] ?? []);

        return $unitOfWork;
    }
}
