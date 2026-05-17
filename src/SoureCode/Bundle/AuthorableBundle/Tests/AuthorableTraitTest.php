<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\AuthorableBundle\Doctrine\AuthorableTrait;
use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\StubUser;
use SoureCode\Component\Authorable\Attribute\CreatedBy;
use SoureCode\Component\Authorable\Attribute\UpdatedBy;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class AuthorableTraitTest extends TestCase
{
    public function testTraitExposesUserInterfaceAccessors(): void
    {
        $entity = new class {
            use AuthorableTrait;
        };

        $user = new StubUser('alice');
        $entity->setCreatedBy($user);
        $entity->setUpdatedBy($user);

        self::assertSame($user, $entity->getCreatedBy());
        self::assertSame($user, $entity->getUpdatedBy());
    }

    public function testTraitPropertiesCarryAuthorableAttributes(): void
    {
        $class = new \ReflectionClass(new class {
            use AuthorableTrait;
        });

        $createdAt = $class->getProperty('createdBy');
        $updatedAt = $class->getProperty('updatedBy');

        self::assertNotEmpty($createdAt->getAttributes(CreatedBy::class));
        self::assertNotEmpty($updatedAt->getAttributes(UpdatedBy::class));
    }

    public function testMetadataFactoryPicksUpTraitBindings(): void
    {
        $entityClass = (new class {
            use AuthorableTrait;
        })::class;

        $metadata = (new AuthorableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getPersistBindings());
        self::assertCount(1, $metadata->getUpdateBindings());
        self::assertSame('createdBy', $metadata->getPersistBindings()[0]->getProperty()->getName());
        self::assertSame('updatedBy', $metadata->getUpdateBindings()[0]->getProperty()->getName());
    }
}
