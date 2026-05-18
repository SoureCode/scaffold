<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\AuthorableBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Tests\Fixtures\StubUser;
use SoureCode\Component\Authorable\Attribute\CreatedBy;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class CreatedByTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use CreatedByTrait;
        };

        $user = new StubUser('alice');
        $entity->setCreatedBy($user);

        self::assertSame($user, $entity->getCreatedBy());
    }

    public function testTraitPropertyCarriesCreatedByAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use CreatedByTrait;
        });

        self::assertNotEmpty($class->getProperty('createdBy')->getAttributes(CreatedBy::class));
    }

    public function testMetadataFactoryPicksUpTraitBinding(): void
    {
        $entityClass = (new class {
            use CreatedByTrait;
        })::class;

        $metadata = (new AuthorableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getPersistBindings());
        self::assertSame('createdBy', $metadata->getPersistBindings()[0]->getProperty()->getName());
    }
}
