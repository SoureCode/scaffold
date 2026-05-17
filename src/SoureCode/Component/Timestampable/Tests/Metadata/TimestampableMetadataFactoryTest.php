<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Timestampable\Metadata\TimestampableMetadataFactory;
use SoureCode\Component\Timestampable\Tests\Fixtures\Article;
use SoureCode\Component\Timestampable\Tests\Fixtures\Note;

final class TimestampableMetadataFactoryTest extends TestCase
{
    public function testFindsAttributePropertiesOnEntity(): void
    {
        $factory = new TimestampableMetadataFactory();

        $metadata = $factory->getMetadataFor(Note::class);

        self::assertCount(1, $metadata->createdBindings);
        self::assertCount(1, $metadata->updatedBindings);
        self::assertSame('writtenAt', $metadata->createdBindings[0]->property->getName());
        self::assertSame('editedAt', $metadata->updatedBindings[0]->property->getName());
    }

    public function testFindsAttributePropertiesOnTrait(): void
    {
        $factory = new TimestampableMetadataFactory();

        $metadata = $factory->getMetadataFor(Article::class);

        self::assertSame('createdAt', $metadata->createdBindings[0]->property->getName());
        self::assertSame('updatedAt', $metadata->updatedBindings[0]->property->getName());
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
