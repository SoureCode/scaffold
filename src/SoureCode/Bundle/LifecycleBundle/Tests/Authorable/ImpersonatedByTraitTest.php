<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\LifecycleBundle\Doctrine\ImpersonatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Fixtures\StubUser;
use SoureCode\Component\Lifecycle\Attribute\ImpersonatedBy;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;

final class ImpersonatedByTraitTest extends TestCase
{
    public function testTraitExposesAccessors(): void
    {
        $entity = new class {
            use ImpersonatedByTrait;
        };

        $user = new StubUser('alice');
        $entity->setImpersonatedBy($user);

        self::assertSame($user, $entity->getImpersonatedBy());
    }

    public function testTraitPropertyCarriesImpersonatedByAttribute(): void
    {
        $class = new \ReflectionClass(new class {
            use ImpersonatedByTrait;
        });

        self::assertNotEmpty($class->getProperty('impersonatedBy')->getAttributes(ImpersonatedBy::class));
    }

    public function testMetadataFactoryCollectsImpersonatedBinding(): void
    {
        $entityClass = (new class {
            use ImpersonatedByTrait;
        })::class;

        $metadata = (new AuthorableMetadataFactory())->getMetadataFor($entityClass);

        self::assertCount(1, $metadata->getImpersonatedBindings());
        self::assertSame('impersonatedBy', $metadata->getImpersonatedBindings()[0]->getProperty()->getName());
    }
}
