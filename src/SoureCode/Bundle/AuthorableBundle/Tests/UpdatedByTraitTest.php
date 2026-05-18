<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\AuthorableBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\StubUser;
use SoureCode\Component\Authorable\Attribute\UpdatedBy;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class UpdatedByTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use UpdatedByTrait;
        };

        $user = new StubUser('alice');
        $entity->setUpdatedBy($user);

        self::assertSame($user, $entity->getUpdatedBy());
    }

    public function testTraitPropertyCarriesUpdatedByAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use UpdatedByTrait;
        });

        self::assertNotEmpty($class->getProperty('updatedBy')->getAttributes(UpdatedBy::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use UpdatedByTrait;
        })::class;

        $metadata = (new AuthorableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getUpdateBindings());
        self::assertSame('updatedBy', $metadata->getUpdateBindings()[0]->getProperty()->getName());
    }
}
