<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Sample
{
    public ?Sample $parent = null;

    /**
     * @var Collection<int, Sample>
     */
    public Collection $children;

    public function __construct(
        public string $label = 'sample',
    ) {
        $this->children = new ArrayCollection();
    }
}
