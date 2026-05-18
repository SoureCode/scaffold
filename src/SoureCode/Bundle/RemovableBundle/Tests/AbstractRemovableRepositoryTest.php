<?php

declare(strict_types=1);

namespace SoureCode\Bundle\RemovableBundle\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Bundle\RemovableBundle\Doctrine\AbstractRemovableRepository;
use SoureCode\Component\Removable\Repository\RemovableRepositoryTrait;

final class AbstractRemovableRepositoryTest extends TestCase
{
    public function testAbstractRepositoryUsesRemovableTrait(): void
    {
        $reflection = new \ReflectionClass(AbstractRemovableRepository::class);
        $traitNames = $reflection->getTraitNames();

        self::assertContains(RemovableRepositoryTrait::class, $traitNames);
    }

    public function testAbstractRepositoryExposesRemoveAndRestore(): void
    {
        $reflection = new \ReflectionClass(AbstractRemovableRepository::class);

        self::assertTrue($reflection->hasMethod('remove'));
        self::assertTrue($reflection->hasMethod('restore'));

        $remove = $reflection->getMethod('remove');
        self::assertTrue($remove->isPublic());

        $restore = $reflection->getMethod('restore');
        self::assertTrue($restore->isPublic());
    }
}
