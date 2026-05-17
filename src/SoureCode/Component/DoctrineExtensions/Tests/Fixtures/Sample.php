<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

final class Sample
{
    public ?Sample $parent = null;

    public function __construct(
        public string $label = 'sample',
    ) {
    }
}
