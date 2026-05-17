<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\ChangeSet;

use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\ChangeSet\ChangeSetMatcher;
use SoureCode\Component\Timestampable\Metadata\ChangedAtBinding;
use SoureCode\Component\Timestampable\Tests\Fixtures\Post;
use SoureCode\Component\Timestampable\Tests\Fixtures\Status;

final class ChangeSetMatcherTest extends TestCase
{
    public function testFiresWhenAnyWatchedFieldInChangeset(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'contentChangedAt', ['title', 'body']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['title' => ['old', 'new']],
        ]);

        self::assertTrue($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testIgnoresUnrelatedChange(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'contentChangedAt', ['title', 'body']);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['somethingElse' => ['a', 'b']],
        ]);

        self::assertFalse($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testValueMatcherFiresOnEnumMatch(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'publishedAt', ['status'], matchValue: true, value: Status::Published);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['status' => [Status::Draft, Status::Published]],
        ]);

        self::assertTrue($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testValueMatcherAcceptsScalarFormOfEnum(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'publishedAt', ['status'], matchValue: true, value: Status::Published);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['status' => ['draft', 'published']],
        ]);

        self::assertTrue($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testValueMatcherRejectsDifferentEnumCase(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'publishedAt', ['status'], matchValue: true, value: Status::Published);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['status' => [Status::Draft, Status::Archived]],
        ]);

        self::assertFalse($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testNullValueMatcherFiresOnClearedField(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'publishedAt', ['status'], matchValue: true, value: null);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['status' => [Status::Draft, null]],
        ]);

        self::assertTrue($matcher->matches($binding, $post, $unitOfWork));
    }

    public function testNullValueMatcherRejectsNonNullNewValue(): void
    {
        $matcher = new ChangeSetMatcher();
        $post = new Post('hello');
        $binding = $this->makeBinding($post, 'publishedAt', ['status'], matchValue: true, value: null);
        $unitOfWork = $this->mockUnitOfWork([
            spl_object_id($post) => ['status' => [Status::Draft, Status::Published]],
        ]);

        self::assertFalse($matcher->matches($binding, $post, $unitOfWork));
    }

    /**
     * @param list<string> $fields
     */
    private function makeBinding(object $entity, string $propertyName, array $fields, bool $matchValue = false, mixed $value = null): ChangedAtBinding
    {
        return new ChangedAtBinding(
            new \ReflectionProperty($entity::class, $propertyName),
            $fields,
            $matchValue,
            $value,
            'datetimetz_immutable',
        );
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
