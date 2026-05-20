<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

interface StampableInterface
{
    public function getInterfaceStamp(): ?string;

    public function setInterfaceStamp(?string $stamp): void;
}
