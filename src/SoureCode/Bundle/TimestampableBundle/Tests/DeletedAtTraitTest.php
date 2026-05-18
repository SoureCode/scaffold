<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TimestampableBundle\Doctrine\DeletedAtTrait;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

final class DeletedAtTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use DeletedAtTrait;
        };

        self::assertNull($entity->getDeletedAt());

        $now = new \DateTimeImmutable('2026-05-17T10:00:00+00:00');
        $entity->setDeletedAt($now);

        self::assertSame($now, $entity->getDeletedAt());
    }

    public function testTraitPropertyCarriesDeletedAtAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use DeletedAtTrait;
        });

        self::assertNotEmpty($class->getProperty('deletedAt')->getAttributes(DeletedAt::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use DeletedAtTrait;
        })::class;

        $metadata = (new TimestampableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getDeletedBindings());
    }
}
