<?php

declare(strict_types=1);

namespace SoureCode\Bundle\LifecycleBundle\Tests\Authorable\Support;

use SoureCode\Component\Lifecycle\Author\ImpersonatorProviderInterface;

final class FixedImpersonatorProvider implements ImpersonatorProviderInterface
{
    private ?object $impersonator = null;

    public function setImpersonator(?object $impersonator): void
    {
        $this->impersonator = $impersonator;
    }

    public function getImpersonator(): ?object
    {
        return $this->impersonator;
    }
}
