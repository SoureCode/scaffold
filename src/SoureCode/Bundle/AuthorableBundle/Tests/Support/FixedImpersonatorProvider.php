<?php

declare(strict_types=1);

namespace SoureCode\Bundle\AuthorableBundle\Tests\Support;

use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;

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
