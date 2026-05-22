<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Metadata;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Lifecycle\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\Article;
use SoureCode\Component\Lifecycle\Tests\Timestampable\Fixtures\Note;

final class TimestampableMetadataFactoryTest extends TestCase
{
    public function testFindsAttributePropertiesOnEntity(): void
    {
        $factory = new TimestampableMetadataFactory();

        $metadata = $factory->getMetadataFor(Note::class);

        self::assertCount(1, $metadata->getPersistBindings());
        self::assertCount(1, $metadata->getUpdateBindings());
        self::assertSame('writtenAt', $metadata->getPersistBindings()[0]->getProperty()->getName());
        self::assertSame('editedAt', $metadata->getUpdateBindings()[0]->getProperty()->getName());
    }

    public function testFindsAttributePropertiesOnTrait(): void
    {
        $factory = new TimestampableMetadataFactory();

        $metadata = $factory->getMetadataFor(Article::class);

        self::assertSame('createdAt', $metadata->getPersistBindings()[0]->getProperty()->getName());
        self::assertSame('updatedAt', $metadata->getUpdateBindings()[0]->getProperty()->getName());
    }

    public function testReturnsEmptyMetadataForPlainClass(): void
    {
        $factory = new TimestampableMetadataFactory();

        $metadata = $factory->getMetadataFor(\stdClass::class);

        self::assertTrue($metadata->isEmpty());
    }

    public function testCachesResultPerClass(): void
    {
        $factory = new TimestampableMetadataFactory();

        $first = $factory->getMetadataFor(Note::class);
        $second = $factory->getMetadataFor(Note::class);

        self::assertSame($first, $second);
    }
}
