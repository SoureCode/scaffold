<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TimestampableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Component\Timestampable\Attribute\UpdatedAt;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;

final class UpdatedAtTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use UpdatedAtTrait;
        };

        $now = new \DateTimeImmutable('2026-05-17T10:00:00+00:00');
        $entity->setUpdatedAt($now);

        self::assertSame($now, $entity->getUpdatedAt());
    }

    public function testTraitPropertyCarriesUpdatedAtAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use UpdatedAtTrait;
        });

        self::assertNotEmpty($class->getProperty('updatedAt')->getAttributes(UpdatedAt::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use UpdatedAtTrait;
        })::class;

        $metadata = (new TimestampableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getUpdateBindings());
        self::assertSame('updatedAt', $metadata->getUpdateBindings()[0]->getProperty()->getName());
    }
}
