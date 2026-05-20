<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\ChangeSet;

use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\Sample;

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
