<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\LifecycleBundle\Doctrine\DeletedByTrait;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\StubUser;
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;

final class DeletedByTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use DeletedByTrait;
        };

        self::assertNull($entity->getDeletedBy());

        $user = new StubUser('alice');
        $entity->setDeletedBy($user);

        self::assertSame($user, $entity->getDeletedBy());
    }

    public function testTraitPropertyCarriesDeletedByAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use DeletedByTrait;
        });

        $property = $class->getProperty('deletedBy');

        self::assertNotEmpty($property->getAttributes(DeletedBy::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use DeletedByTrait;
        })::class;

        $metadata = (new AuthorableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getDeletedBindings());
        self::assertSame('deletedBy', $metadata->getDeletedBindings()[0]->getProperty()->getName());
    }
}
