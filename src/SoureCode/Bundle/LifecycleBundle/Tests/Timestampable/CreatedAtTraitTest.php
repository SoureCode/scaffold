<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Timestampable;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedAtTrait;
use SoureCode\Component\Lifecycle\Attribute\CreatedAt;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;

final class CreatedAtTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use CreatedAtTrait;
        };

        $now = new \DateTimeImmutable('2026-05-17T10:00:00+00:00');
        $entity->setCreatedAt($now);

        self::assertSame($now, $entity->getCreatedAt());
    }

    public function testTraitPropertyCarriesCreatedAtAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use CreatedAtTrait;
        });

        self::assertNotEmpty($class->getProperty('createdAt')->getAttributes(CreatedAt::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use CreatedAtTrait;
        })::class;

        $metadata = (new TimestampableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getPersistBindings());
        self::assertSame('createdAt', $metadata->getPersistBindings()[0]->getProperty()->getName());
    }
}
