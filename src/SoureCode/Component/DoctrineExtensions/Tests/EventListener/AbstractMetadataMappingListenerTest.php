<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadata;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeChangeBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakePersistBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\FakeUpdateBinding;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\RecordingMappingListener;
use SoureCode\Component\DoctrineExtensions\Tests\Fixtures\Stampable;

final class AbstractMetadataMappingListenerTest extends TestCase
{
    public function testLoadClassMetadataBailsWhenMetadataIsEmpty(): void
    {
        $listener = new RecordingMappingListener(new FakeBehaviorMetadataFactory());

        $listener->loadClassMetadata($this->eventArgsFor(Stampable::class));

        self::assertSame([], $listener->calls, 'empty metadata must not produce any mapIfMissing calls');
    }

    public function testLoadClassMetadataAppliesNullabilityConventionAcrossAllBucketTypes(): void
    {
        $persistProperty = new \ReflectionProperty(Stampable::class, 'persistStamp');
        $updateNonNullProperty = new \ReflectionProperty(Stampable::class, 'updateStamp');
        $updateNullableProperty = new \ReflectionProperty(Stampable::class, 'label');
        $changeProperty = new \ReflectionProperty(Stampable::class, 'label');
        $deletedProperty = new \ReflectionProperty(Stampable::class, 'persistStamp');

        $metadata = new FakeBehaviorMetadata(
            persistBindings: [new FakePersistBinding($persistProperty)],
            updateBindings: [
                new FakeUpdateBinding($updateNonNullProperty, nullable: false),
                new FakeUpdateBinding($updateNullableProperty, nullable: true),
            ],
            changeBindings: [new FakeChangeBinding($changeProperty, ['label'])],
            deletedBindings: [new FakePersistBinding($deletedProperty)],
        );

        $listener = new RecordingMappingListener(
            new FakeBehaviorMetadataFactory([Stampable::class => $metadata]),
        );

        $listener->loadClassMetadata($this->eventArgsFor(Stampable::class));

        // The base must visit every bucket in order, with the correct nullability:
        //   persist  → false
        //   update   → binding.isNullable()
        //   change   → true
        //   deleted  → true
        $expectedShape = [
            ['property' => 'persistStamp', 'nullable' => false],
            ['property' => 'updateStamp',  'nullable' => false],
            ['property' => 'label',        'nullable' => true],
            ['property' => 'label',        'nullable' => true],
            ['property' => 'persistStamp', 'nullable' => true],
        ];

        $actualShape = array_map(
            static fn (array $call): array => [
                'property' => $call['binding']->getProperty()->getName(),
                'nullable' => $call['nullable'],
            ],
            $listener->calls,
        );

        self::assertSame($expectedShape, $actualShape);
    }

    public function testLoadClassMetadataInvokesGetDeletedBindingsHook(): void
    {
        $persistProperty = new \ReflectionProperty(Stampable::class, 'persistStamp');
        $deletedProperty = new \ReflectionProperty(Stampable::class, 'updateStamp');

        $metadata = new FakeBehaviorMetadata(
            persistBindings: [new FakePersistBinding($persistProperty)],
            deletedBindings: [new FakePersistBinding($deletedProperty)],
        );

        $listener = new RecordingMappingListener(
            new FakeBehaviorMetadataFactory([Stampable::class => $metadata]),
        );

        $listener->loadClassMetadata($this->eventArgsFor(Stampable::class));

        self::assertCount(2, $listener->calls);
        self::assertSame('updateStamp', $listener->calls[1]['binding']->getProperty()->getName());
        self::assertTrue($listener->calls[1]['nullable'], 'deleted bindings are always nullable=true');
    }

    public function testLoadClassMetadataLeavesIrrelevantBucketsAlone(): void
    {
        $changeProperty = new \ReflectionProperty(Stampable::class, 'label');

        $metadata = new FakeBehaviorMetadata(
            changeBindings: [new FakeChangeBinding($changeProperty, ['label'])],
        );

        $listener = new RecordingMappingListener(
            new FakeBehaviorMetadataFactory([Stampable::class => $metadata]),
        );

        $listener->loadClassMetadata($this->eventArgsFor(Stampable::class));

        self::assertCount(1, $listener->calls);
        self::assertSame('label', $listener->calls[0]['binding']->getProperty()->getName());
        self::assertTrue($listener->calls[0]['nullable']);
    }

    private function eventArgsFor(string $entityClass): LoadClassMetadataEventArgs
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn($entityClass);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new LoadClassMetadataEventArgs($classMetadata, $entityManager);
    }
}
