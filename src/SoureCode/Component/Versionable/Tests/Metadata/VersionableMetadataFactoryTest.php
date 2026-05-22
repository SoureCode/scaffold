<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Metadata;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use SoureCode\Component\Versionable\Attribute\Versioned;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;

final class VersionableMetadataFactoryTest extends TestCase
{
    public function testReturnsEmptyMetadataForUnannotatedClass(): void
    {
        $factory = new VersionableMetadataFactory();

        self::assertTrue($factory->getMetadataFor(VersionableMetadataFactoryTestPlain::class)->isEmpty());
        self::assertFalse($factory->isVersionable(VersionableMetadataFactoryTestPlain::class));
    }

    public function testCollectsVersionedAttributesFromDeclaringClass(): void
    {
        $factory = new VersionableMetadataFactory();
        $metadata = $factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);

        self::assertFalse($metadata->isEmpty());
        self::assertTrue($factory->isVersionable(VersionableMetadataFactoryTestEntity::class));
        self::assertCount(1, $metadata->bindings);
        self::assertSame('title', $metadata->bindings[0]->property->getName());
    }

    public function testCollectsAttributesFromParentClass(): void
    {
        $factory = new VersionableMetadataFactory();
        $metadata = $factory->getMetadataFor(VersionableMetadataFactoryTestChild::class);

        self::assertCount(1, $metadata->bindings, 'Inherited #[Versioned] property must yield exactly one binding');
        self::assertSame('title', $metadata->bindings[0]->property->getName());
        self::assertSame(
            VersionableMetadataFactoryTestEntity::class,
            $metadata->bindings[0]->property->getDeclaringClass()->getName(),
        );
    }

    public function testCachesMetadataPerClass(): void
    {
        $factory = new VersionableMetadataFactory();

        $first = $factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);
        $second = $factory->getMetadataFor(VersionableMetadataFactoryTestEntity::class);

        self::assertSame($first, $second);
    }

    public function testVersionTableNameAppendsSuffix(): void
    {
        $factory = new VersionableMetadataFactory();

        self::assertSame('article_version', $factory->versionTableName('article'));
        self::assertSame('user_profile_version', $factory->versionTableName('user_profile'));
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_plain')]
class VersionableMetadataFactoryTestPlain
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_entity')]
class VersionableMetadataFactoryTestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Versioned]
    #[ORM\Column(type: Types::STRING)]
    public string $title = '';
}

#[ORM\Entity]
#[ORM\Table(name: 'versionable_metadata_factory_test_child')]
class VersionableMetadataFactoryTestChild extends VersionableMetadataFactoryTestEntity
{
}
